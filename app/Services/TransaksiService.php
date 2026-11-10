<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\StokMutasi;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;

/**
 * Nota penjualan. Semua angka dihitung ulang di server (input client hanya qty, harga jual,
 * dan harga beli untuk item supplier).
 */
class TransaksiService
{
    public function __construct(
        private StokService $stok,
        private NomorService $nomor,
        private TagihanSupplierService $tagihanSupplier,
    ) {
    }

    public function simpan(array $data, ?Transaksi $transaksi = null): Transaksi
    {
        return DB::transaction(function () use ($data, $transaksi) {
            $items = collect($data['items'])->map(fn ($i) => [
                'barang_id' => (int) $i['barang_id'],
                'sumber' => $i['sumber'],
                'supplier_id' => $i['sumber'] === 'supplier' ? (int) $i['supplier_id'] : null,
                'qty' => round((float) $i['qty'], 2),
                'harga_beli' => round((float) ($i['harga_beli'] ?? 0), 2),
                'harga_jual' => round((float) $i['harga_jual'], 2),
            ]);

            $detailLama = $transaksi ? $transaksi->details()->get() : collect();
            $stokLama = $detailLama->where('sumber', 'stok');

            // Kunci semua barang sumber stok (baru & lama)
            $barangs = $this->stok->kunciBarang(
                $items->where('sumber', 'stok')->pluck('barang_id')->merge($stokLama->pluck('barang_id'))
            );

            $tanggalLama = null;
            // [supplier_id, tanggal] belanja lama: pembayaran supplier per tanggal dicek ulang setelah disimpan
            $belanjaLama = $detailLama->where('sumber', 'supplier')->pluck('supplier_id')->unique()->values();
            if ($transaksi) {
                $transaksi = Transaksi::whereKey($transaksi->id)->lockForUpdate()->firstOrFail();
                $tanggalLama = $transaksi->tanggal->toDateString();
            }

            // Validasi stok: qty yang diminta ≤ stok sekarang + qty yang dulu dipakai nota ini
            $qtyLama = $stokLama->groupBy('barang_id')->map(fn ($g) => (float) $g->sum('qty'));
            foreach ($items->where('sumber', 'stok')->groupBy('barang_id') as $barangId => $group) {
                $barang = $barangs[$barangId];
                $tersedia = (float) $barang->stok + ($qtyLama[$barangId] ?? 0);
                $diminta = (float) $group->sum('qty');

                if ($diminta > $tersedia + 0.0001) {
                    throw new BisnisException(sprintf(
                        'Stok %s tidak cukup. Tersedia %s %s, diminta %s %s.',
                        $barang->nama, qty($tersedia), $barang->satuan, qty($diminta), $barang->satuan
                    ));
                }
            }

            // Harga beli item stok = HPP saat transaksi (dikunci).
            // Saat edit, barang yang sudah ada di nota mempertahankan HPP lamanya.
            $hppTerkunci = $stokLama->pluck('harga_beli', 'barang_id')->union($data['hpp_terkunci'] ?? []); // hpp_terkunci: dari riwayat hapus
            $items = $items->map(function ($i) use ($barangs, $hppTerkunci) {
                if ($i['sumber'] === 'stok') {
                    $i['harga_beli'] = round((float) ($hppTerkunci[$i['barang_id']] ?? $barangs[$i['barang_id']]->hpp), 2);
                }
                $i['subtotal_beli'] = round($i['qty'] * $i['harga_beli'], 2);
                $i['subtotal_jual'] = round($i['qty'] * $i['harga_jual'], 2);

                return $i;
            });

            $totalJual = round($items->sum('subtotal_jual'), 2);
            $totalModal = round($items->sum('subtotal_beli'), 2);

            if ($transaksi) {
                $dibayar = (float) $transaksi->pembayarans()->sum('jumlah');
                if ($dibayar > $totalJual) {
                    throw new BisnisException('Total jual baru (' . rupiah($totalJual) . ') lebih kecil dari pembayaran yang sudah diterima (' . rupiah($dibayar) . ').');
                }

                $transaksi->details()->delete();
                $this->stok->hapusReferensi(StokMutasi::REF_TRANSAKSI, $transaksi->id);
            } else {
                $transaksi = new Transaksi([
                    'no_nota' => $data['no_nota'] ?? $this->nomor->buat(Transaksi::class, 'no_nota', 'NJ', $data['tanggal']),
                ]);
            }

            $transaksi->fill([
                'tanggal' => $data['tanggal'],
                'klien_id' => $data['klien_id'],
                'keterangan' => $data['keterangan'] ?? null,
                'total_qty' => round($items->sum('qty'), 2),
                'total_jual' => $totalJual,
                'total_modal' => $totalModal,
                'total_belanja_supplier' => round($items->where('sumber', 'supplier')->sum('subtotal_beli'), 2),
                'laba' => round($totalJual - $totalModal, 2),
            ])->save();

            $transaksi->details()->createMany($items->all());

            // Item dari stok: stok berkurang (tidak membuat cashflow)
            foreach ($items->where('sumber', 'stok') as $i) {
                $this->stok->catat($i['barang_id'], 'keluar', $data['tanggal'], $i['qty'], $i['harga_beli'],
                    StokMutasi::REF_TRANSAKSI, $transaksi->id, "Nota {$transaksi->no_nota}");
            }
            $this->stok->hitungUlangBanyak($barangs);

            $transaksi->perbaruiStatusBayar();

            // Belanja supplier dibayar lewat menu Pembayaran (per supplier per tanggal nota).
            // Saat edit, pastikan pembayaran yang sudah dicatat tidak melebihi belanja barunya.
            if ($tanggalLama) {
                $this->tagihanSupplier->pastikanTidakLebih($belanjaLama->map(fn ($id) => [(int) $id, $tanggalLama]));
            }

            return $transaksi;
        });
    }

    public function hapus(Transaksi $transaksi): void
    {
        DB::transaction(function () use ($transaksi) {
            if ($transaksi->pembayarans()->exists()) {
                throw new BisnisException("Nota {$transaksi->no_nota} sudah memiliki pembayaran. Hapus pembayarannya dulu.");
            }

            $barangs = $this->stok->kunciBarang(
                $transaksi->details()->where('sumber', 'stok')->pluck('barang_id')
            );
            $tanggal = $transaksi->tanggal->toDateString();
            $supplierIds = $transaksi->details()->where('sumber', 'supplier')->distinct()->pluck('supplier_id');

            $this->stok->hapusReferensi(StokMutasi::REF_TRANSAKSI, $transaksi->id);
            $transaksi->delete(); // detail ikut terhapus (cascade)

            $this->stok->hitungUlangBanyak($barangs); // stok kembali
            // Belanja supplier hari itu tidak boleh lebih kecil dari pembayaran yang sudah dicatat
            $this->tagihanSupplier->pastikanTidakLebih($supplierIds->map(fn ($id) => [(int) $id, $tanggal]));
        });
    }
}
