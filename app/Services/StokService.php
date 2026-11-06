<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\Barang;
use App\Models\StokMutasi;
use App\Models\StokOpname;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Semua perubahan stok & HPP lewat class ini.
 *
 * Pola kerja: setiap dokumen (pembelian/nota/opname) menulis baris mutasi,
 * lalu hitungUlang() memutar ulang seluruh mutasi barang (urut tanggal, id) untuk
 * mendapatkan stok, HPP rata-rata tertimbang, dan saldo berjalan kartu stok.
 * Dengan begitu edit/hapus dokumen lama (termasuk tanggal mundur) selalu konsisten.
 *
 * Wajib dipanggil di dalam DB::transaction() setelah kunciBarang().
 */
class StokService
{
    /**
     * Kunci baris barang (SELECT ... FOR UPDATE), urut id agar bebas deadlock.
     *
     * @return Collection<int, Barang> keyBy id
     */
    public function kunciBarang(iterable $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Barang::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /** Tulis satu baris mutasi. stok_sebelum/sesudah diisi oleh hitungUlang(). */
    public function catat(
        int $barangId,
        string $tipe,
        $tanggal,
        float $qty,
        float $harga,
        string $referensiTipe,
        ?int $referensiId,
        ?string $keterangan = null
    ): StokMutasi {
        return StokMutasi::create([
            'barang_id' => $barangId,
            'tanggal' => $tanggal,
            'tipe' => $tipe,
            'qty' => round($qty, 2),
            'harga' => round($harga, 2),
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'keterangan' => $keterangan,
        ]);
    }

    /**
     * Hapus mutasi milik satu dokumen.
     *
     * @return array<int> id barang yang terdampak
     */
    public function hapusReferensi(string $referensiTipe, int $referensiId): array
    {
        $query = StokMutasi::where('referensi_tipe', $referensiTipe)->where('referensi_id', $referensiId);
        $barangIds = (clone $query)->pluck('barang_id')->unique()->values()->all();
        $query->delete();

        return $barangIds;
    }

    /**
     * Putar ulang mutasi → stok akhir, HPP rata-rata tertimbang, saldo berjalan.
     *
     * Rumus saat barang masuk:
     *   hpp_baru = ((stok_lama × hpp_lama) + (qty_beli × harga_beli)) / (stok_lama + qty_beli)
     * Barang keluar & penyesuaian tidak mengubah HPP.
     *
     * @throws BisnisException jika stok akhir menjadi minus
     */
    public function hitungUlang(Barang $barang): Barang
    {
        $stok = 0.0;
        $hpp = 0.0;

        $mutasis = StokMutasi::where('barang_id', $barang->id)
            ->orderBy('tanggal')->orderBy('id')
            ->get(['id', 'tipe', 'qty', 'harga', 'stok_sebelum', 'stok_sesudah']);

        foreach ($mutasis as $m) {
            $sebelum = $stok;
            $qty = (float) $m->qty;

            if ($m->tipe === 'masuk') {
                $stokBaru = $stok + $qty;
                $hpp = ($stok > 0 && $stokBaru > 0)
                    ? (($stok * $hpp) + ($qty * (float) $m->harga)) / $stokBaru
                    : (float) $m->harga;
                $stok = $stokBaru;
            } elseif ($m->tipe === 'keluar') {
                $stok -= $qty;
            } else { // penyesuaian (qty bertanda)
                $stok += $qty;
            }

            // Simpan saldo berjalan hanya jika berubah (hemat query)
            if (round((float) $m->stok_sebelum, 2) !== round($sebelum, 2) || round((float) $m->stok_sesudah, 2) !== round($stok, 2)) {
                StokMutasi::whereKey($m->id)->update([
                    'stok_sebelum' => round($sebelum, 2),
                    'stok_sesudah' => round($stok, 2),
                ]);
            }
        }

        if (round($stok, 2) < 0) {
            throw new BisnisException(sprintf(
                'Stok %s akan menjadi minus (%s %s). Proses dibatalkan.',
                $barang->nama, qty($stok), $barang->satuan
            ));
        }

        $barang->forceFill(['stok' => round($stok, 2), 'hpp' => round($hpp, 2)])->save();

        return $barang;
    }

    /** @param iterable<Barang> $barangs */
    public function hitungUlangBanyak(iterable $barangs): void
    {
        foreach ($barangs as $barang) {
            $this->hitungUlang($barang);
        }
    }

    /** Stok awal saat barang baru dibuat (tercatat sebagai mutasi masuk). */
    public function stokAwal(Barang $barang, float $qty, float $hpp): void
    {
        if ($qty <= 0) {
            return;
        }

        $this->catat($barang->id, 'masuk', now()->toDateString(), $qty, $hpp, StokMutasi::REF_STOK_AWAL, $barang->id, 'Stok awal');
        $this->hitungUlang($barang);
    }

    /**
     * Stok opname: stok fisik menggantikan stok sistem, selisih dicatat sebagai penyesuaian.
     * Tidak memengaruhi cashflow; nilai_selisih (selisih × HPP) tampil di laporan.
     */
    public function simpanOpname(array $data): StokOpname
    {
        return DB::transaction(function () use ($data) {
            $barang = $this->kunciBarang([$data['barang_id']])->first();

            $stokSistem = (float) $barang->stok;
            $stokFisik = round((float) $data['stok_fisik'], 2);
            $selisih = round($stokFisik - $stokSistem, 2);

            $opname = StokOpname::create([
                'tanggal' => $data['tanggal'],
                'barang_id' => $barang->id,
                'stok_sistem' => $stokSistem,
                'stok_fisik' => $stokFisik,
                'selisih' => $selisih,
                'nilai_selisih' => round($selisih * (float) $barang->hpp, 2),
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            if ($selisih != 0) {
                $this->catat($barang->id, 'penyesuaian', $data['tanggal'], $selisih, (float) $barang->hpp,
                    StokMutasi::REF_OPNAME, $opname->id, 'Stok opname' . (! empty($data['keterangan']) ? ': ' . $data['keterangan'] : ''));
                $this->hitungUlang($barang);
            }

            return $opname;
        });
    }

    /** Batalkan opname: mutasi penyesuaian dihapus & stok dihitung ulang. */
    public function hapusOpname(StokOpname $opname): void
    {
        DB::transaction(function () use ($opname) {
            $barangs = $this->kunciBarang([$opname->barang_id]);
            $this->hapusReferensi(StokMutasi::REF_OPNAME, $opname->id);
            $opname->delete();
            $this->hitungUlangBanyak($barangs);
        });
    }
}
