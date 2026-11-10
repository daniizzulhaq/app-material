<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\Barang;
use App\Models\Cashflow;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\RiwayatHapus;
use App\Models\StokMutasi;
use App\Models\StokOpname;
use App\Models\Supplier;
use App\Models\Transaksi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat hapus: setiap data yang dihapus disimpan sebagai snapshot,
 * lalu bisa dipulihkan lewat service aslinya (stok, HPP, cashflow & status bayar ikut dihitung ulang).
 */
class RiwayatService
{
    public function __construct(
        private StokService $stok,
        private TransaksiService $transaksi,
        private PembelianStokService $pembelian,
    ) {
    }

    /** Simpan snapshot lalu jalankan $hapus, dalam satu transaksi DB (gagal hapus = riwayat batal). */
    public function hapus(Model $model, callable $hapus): RiwayatHapus
    {
        return DB::transaction(function () use ($model, $hapus) {
            $riwayat = RiwayatHapus::create($this->snapshot($model) + ['dihapus_oleh' => auth()->id()]);
            $hapus();

            return $riwayat;
        });
    }

    private function snapshot(Model $m): array
    {
        return match (true) {
            $m instanceof Supplier => [
                'jenis' => 'supplier', 'label' => $m->nama, 'ringkasan' => $m->no_hp,
                'data' => $m->only(['id', ...$m->getFillable()]),
            ],
            $m instanceof Klien => [
                'jenis' => 'klien', 'label' => $m->nama, 'ringkasan' => $m->no_hp,
                'data' => $m->only(['id', ...$m->getFillable()]),
            ],
            $m instanceof Barang => $this->snapshotBarang($m),
            $m instanceof PembelianStok => $this->snapshotPembelian($m),
            $m instanceof Transaksi => $this->snapshotTransaksi($m),
            $m instanceof StokOpname => $this->snapshotOpname($m),
            $m instanceof Pembayaran => $this->snapshotPembayaran($m),
            $m instanceof Cashflow => [
                'jenis' => 'cashflow',
                'label' => ($m->tipe === 'masuk' ? 'Masuk ' : 'Keluar ') . rupiah($m->nominal),
                'ringkasan' => tanggal($m->tanggal) . ($m->keterangan ? ' · ' . $m->keterangan : ''),
                'data' => ['tanggal' => $m->tanggal->toDateString()] + $m->only(['tipe', 'kategori', 'nominal', 'keterangan']),
            ],
        };
    }

    private function snapshotBarang(Barang $b): array
    {
        $awal = StokMutasi::where('barang_id', $b->id)->where('referensi_tipe', StokMutasi::REF_STOK_AWAL)->first();

        return [
            'jenis' => 'barang', 'label' => $b->nama,
            'ringkasan' => 'Satuan ' . $b->satuan . ' · harga ' . rupiah($b->harga_jual_default),
            'data' => $b->only(['id', ...$b->getFillable()]) + [
                'stok_awal' => $awal ? (float) $awal->qty : 0,
                'hpp_awal' => $awal ? (float) $awal->harga : 0,
            ],
        ];
    }

    private function snapshotPembelian(PembelianStok $p): array
    {
        $p->loadMissing(['supplier', 'details']);

        return [
            'jenis' => 'pembelian_stok', 'label' => $p->no_faktur,
            'ringkasan' => tanggal($p->tanggal) . ' · ' . ($p->supplier->nama ?? '-') . ' · ' . rupiah($p->total),
            'data' => [
                'no_faktur' => $p->no_faktur,
                'tanggal' => $p->tanggal->toDateString(),
                'supplier_id' => $p->supplier_id,
                'metode_bayar' => $p->metode_bayar,
                'keterangan' => $p->keterangan,
                'items' => $p->details->map(fn ($d) => $d->only(['barang_id', 'qty', 'harga_beli']))->all(),
            ],
        ];
    }

    private function snapshotTransaksi(Transaksi $t): array
    {
        $t->loadMissing(['klien', 'details']);

        return [
            'jenis' => 'transaksi', 'label' => $t->no_nota,
            'ringkasan' => tanggal($t->tanggal) . ' · ' . ($t->klien->nama ?? '-') . ' · ' . rupiah($t->total_jual),
            'data' => [
                'no_nota' => $t->no_nota,
                'tanggal' => $t->tanggal->toDateString(),
                'klien_id' => $t->klien_id,
                'keterangan' => $t->keterangan,
                'items' => $t->details->map(fn ($d) => $d->only(['barang_id', 'sumber', 'supplier_id', 'qty', 'harga_beli', 'harga_jual']))->all(),
                // HPP item stok yang terkunci saat nota dibuat, dipakai lagi saat dipulihkan
                'hpp_terkunci' => $t->details->where('sumber', 'stok')->pluck('harga_beli', 'barang_id')->all(),
            ],
        ];
    }

    private function snapshotOpname(StokOpname $o): array
    {
        $o->loadMissing('barang');

        return [
            'jenis' => 'stok_opname', 'label' => 'Opname ' . ($o->barang->nama ?? '-'),
            'ringkasan' => tanggal($o->tanggal) . ' · selisih ' . ($o->selisih > 0 ? '+' : '') . qty($o->selisih),
            'data' => ['tanggal' => $o->tanggal->toDateString()] + $o->only(['barang_id', 'selisih', 'keterangan']),
        ];
    }

    private function snapshotPembayaran(Pembayaran $p): array
    {
        $p->loadMissing(['transaksi', 'pembelianStok', 'klien', 'supplier']);
        $pihak = $p->jenis === 'klien' ? ($p->klien->nama ?? '-') : ($p->supplier->nama ?? '-');

        return [
            'jenis' => 'pembayaran', 'label' => 'Bayar ' . $p->dokumen . ' · ' . rupiah($p->jumlah),
            'ringkasan' => tanggal($p->tanggal) . ' · ' . $pihak,
            'data' => array_merge($p->only($p->getFillable()), [
                'tanggal' => $p->tanggal->toDateString(),
                'tanggal_nota' => $p->tanggal_nota?->toDateString(),
            ]),
        ];
    }

    // ------------------------------------------------------------------ pulihkan

    public function pulihkan(RiwayatHapus $riwayat): string
    {
        return DB::transaction(function () use ($riwayat) {
            $riwayat = RiwayatHapus::whereKey($riwayat->id)->lockForUpdate()->firstOrFail();
            if ($riwayat->dipulihkan_at) {
                throw new BisnisException('Data ini sudah pernah dipulihkan.');
            }

            $d = $riwayat->data;
            $pesan = match ($riwayat->jenis) {
                'supplier' => $this->pulihkanMaster(Supplier::class, $d, 'Supplier'),
                'klien' => $this->pulihkanMaster(Klien::class, $d, 'Klien'),
                'barang' => $this->pulihkanBarang($d),
                'pembelian_stok' => $this->pulihkanPembelian($d),
                'transaksi' => $this->pulihkanTransaksi($d),
                'stok_opname' => $this->pulihkanOpname($d),
                'pembayaran' => $this->pulihkanPembayaran($d),
                'cashflow' => $this->pulihkanCashflow($d),
            };

            $riwayat->update(['dipulihkan_at' => now()]);

            return $pesan;
        });
    }

    private function pulihkanMaster(string $kelas, array $d, string $nama): string
    {
        $this->buatDenganIdLama($kelas, $d);

        return "{$nama} {$d['nama']} berhasil dipulihkan.";
    }

    /**
     * Master dibuat ulang dengan ID aslinya (MySQL tidak memakai ulang ID terhapus),
     * supaya nota/pembelian/pembayaran yang ikut terhapus tetap bisa dipulihkan setelahnya.
     */
    private function buatDenganIdLama(string $kelas, array $d): Model
    {
        $model = new $kelas();
        $model->fill(collect($d)->only($model->getFillable())->all());
        if (! empty($d['id']) && ! $kelas::whereKey($d['id'])->exists()) {
            $model->id = $d['id'];
        }
        $model->save();

        return $model;
    }

    private function pulihkanBarang(array $d): string
    {
        if (! empty($d['supplier_id']) && ! Supplier::whereKey($d['supplier_id'])->exists()) {
            $d['supplier_id'] = null; // supplier langganannya sudah dihapus
        }
        $barang = $this->buatDenganIdLama(Barang::class, $d);
        $this->stok->stokAwal($barang, (float) ($d['stok_awal'] ?? 0), (float) ($d['hpp_awal'] ?? 0));

        return "Barang {$barang->nama} berhasil dipulihkan.";
    }

    private function pulihkanPembelian(array $d): string
    {
        $this->wajibAda(Supplier::class, $d['supplier_id'], 'Supplier pembelian ini sudah dihapus. Pulihkan supplier-nya dulu.');
        $this->wajibAdaBarang(array_column($d['items'], 'barang_id'));

        if (PembelianStok::where('no_faktur', $d['no_faktur'])->exists()) {
            unset($d['no_faktur']); // nomor sudah dipakai → nomor baru
        }
        $p = $this->pembelian->simpan($d);

        return "Pembelian {$p->no_faktur} berhasil dipulihkan. Stok & HPP dihitung ulang.";
    }

    private function pulihkanTransaksi(array $d): string
    {
        $this->wajibAda(Klien::class, $d['klien_id'], 'Klien nota ini sudah dihapus. Pulihkan klien-nya dulu.');
        $this->wajibAdaBarang(array_column($d['items'], 'barang_id'));
        foreach (array_unique(array_filter(array_column($d['items'], 'supplier_id'))) as $sid) {
            $this->wajibAda(Supplier::class, $sid, 'Supplier di salah satu item sudah dihapus. Pulihkan supplier-nya dulu.');
        }

        if (Transaksi::where('no_nota', $d['no_nota'])->exists()) {
            unset($d['no_nota']);
        }
        $t = $this->transaksi->simpan($d);

        return "Nota {$t->no_nota} berhasil dipulihkan. Stok & cashflow dihitung ulang.";
    }

    private function pulihkanOpname(array $d): string
    {
        $this->wajibAdaBarang([$d['barang_id']]);
        $barang = Barang::find($d['barang_id']);

        // Selisih yang sama diterapkan kembali pada stok sekarang
        $opname = $this->stok->simpanOpname([
            'tanggal' => $d['tanggal'],
            'barang_id' => $barang->id,
            'stok_fisik' => (float) $barang->stok + (float) $d['selisih'],
            'keterangan' => $d['keterangan'] ?? null,
        ]);

        return "Opname {$barang->nama} berhasil dipulihkan (selisih " . qty($opname->selisih) . ').';
    }

    private function pulihkanPembayaran(array $d): string
    {
        if ($d['jenis'] === 'supplier' && ! empty($d['tanggal_nota'])) {
            // Pembayaran belanja nota supplier per tanggal
            $this->wajibAda(Supplier::class, $d['supplier_id'], 'Supplier pembayaran ini sudah dihapus. Pulihkan supplier-nya dulu.');
            $tagihan = app(TagihanSupplierService::class)->tanggal((int) $d['supplier_id'], $d['tanggal_nota']);
            if (! $tagihan) {
                throw new BisnisException('Nota supplier pada tanggal pembayaran ini sudah dihapus. Pulihkan notanya dulu.');
            }
            if ((float) $d['jumlah'] > $tagihan->sisa + 0.001) {
                throw new BisnisException('Tidak bisa dipulihkan: jumlah ' . rupiah($d['jumlah']) . ' melebihi sisa tagihan (' . rupiah($tagihan->sisa) . ').');
            }

            Pembayaran::create($d);

            return 'Pembayaran ' . rupiah($d['jumlah']) . ' berhasil dipulihkan.';
        }

        if ($d['jenis'] === 'klien') {
            $tagihan = Transaksi::lockForUpdate()->find($d['transaksi_id']);
            $total = $tagihan?->total_jual;
            $pesanHilang = 'Nota untuk pembayaran ini sudah dihapus. Pulihkan notanya dulu.';
        } else {
            $tagihan = PembelianStok::lockForUpdate()->find($d['pembelian_stok_id']);
            $total = $tagihan?->total;
            $pesanHilang = 'Pembelian untuk pembayaran ini sudah dihapus. Pulihkan pembeliannya dulu.';
        }

        if (! $tagihan) {
            throw new BisnisException($pesanHilang);
        }

        $sisa = (float) $total - (float) $tagihan->pembayarans()->sum('jumlah');
        if ((float) $d['jumlah'] > round($sisa, 2) + 0.001) {
            throw new BisnisException('Tidak bisa dipulihkan: jumlah ' . rupiah($d['jumlah']) . ' melebihi sisa tagihan (' . rupiah($sisa) . ').');
        }

        Pembayaran::create($d); // observer: cashflow & status bayar

        return 'Pembayaran ' . rupiah($d['jumlah']) . ' berhasil dipulihkan.';
    }

    private function pulihkanCashflow(array $d): string
    {
        Cashflow::create($d);

        return 'Cashflow ' . rupiah($d['nominal']) . ' berhasil dipulihkan.';
    }

    private function wajibAda(string $kelas, $id, string $pesan): void
    {
        if (! $kelas::whereKey($id)->exists()) {
            throw new BisnisException($pesan);
        }
    }

    private function wajibAdaBarang(array $ids): void
    {
        $ids = array_unique($ids);
        if (Barang::whereIn('id', $ids)->count() !== count($ids)) {
            throw new BisnisException('Ada barang yang sudah dihapus. Pulihkan barangnya dulu.');
        }
    }
}
