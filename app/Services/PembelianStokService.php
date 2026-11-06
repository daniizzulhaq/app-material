<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\PembelianStok;
use App\Models\StokMutasi;
use Illuminate\Support\Facades\DB;

class PembelianStokService
{
    public function __construct(
        private StokService $stok,
        private CashflowService $cashflow,
        private NomorService $nomor,
    ) {
    }

    /**
     * Simpan pembelian baru / perbarui pembelian lama.
     * Stok bertambah, HPP rata-rata diperbarui, mutasi tercatat, cashflow (jika tunai) disinkronkan.
     */
    public function simpan(array $data, ?PembelianStok $pembelian = null): PembelianStok
    {
        return DB::transaction(function () use ($data, $pembelian) {
            $items = collect($data['items'])->map(fn ($i) => [
                'barang_id' => (int) $i['barang_id'],
                'qty' => round((float) $i['qty'], 2),
                'harga_beli' => round((float) $i['harga_beli'], 2),
            ]);

            $barangLama = $pembelian ? $pembelian->details()->pluck('barang_id') : collect();
            $barangs = $this->stok->kunciBarang($items->pluck('barang_id')->merge($barangLama));

            if ($pembelian) {
                $pembelian = PembelianStok::whereKey($pembelian->id)->lockForUpdate()->firstOrFail();
                $sudahDibayar = (float) $pembelian->pembayarans()->sum('jumlah');

                if ($data['metode_bayar'] === 'tunai' && $sudahDibayar > 0) {
                    throw new BisnisException('Pembelian ini sudah memiliki pembayaran hutang. Hapus pembayarannya dulu sebelum mengubah menjadi tunai.');
                }

                // Koreksi: buang detail & mutasi lama, lalu tulis ulang
                $pembelian->details()->delete();
                $this->stok->hapusReferensi(StokMutasi::REF_PEMBELIAN, $pembelian->id);
            } else {
                $pembelian = new PembelianStok([
                    'no_faktur' => $data['no_faktur'] ?? $this->nomor->buat(PembelianStok::class, 'no_faktur', 'PB', $data['tanggal']),
                ]);
                $sudahDibayar = 0;
            }

            $total = round($items->sum(fn ($i) => round($i['qty'] * $i['harga_beli'], 2)), 2);

            if ($sudahDibayar > $total) {
                throw new BisnisException('Total pembelian (' . rupiah($total) . ') lebih kecil dari yang sudah dibayar (' . rupiah($sudahDibayar) . ').');
            }

            $pembelian->fill([
                'tanggal' => $data['tanggal'],
                'supplier_id' => $data['supplier_id'],
                'metode_bayar' => $data['metode_bayar'],
                'keterangan' => $data['keterangan'] ?? null,
                'total' => $total,
            ])->save();

            foreach ($items as $item) {
                $pembelian->details()->create($item + ['subtotal' => round($item['qty'] * $item['harga_beli'], 2)]);

                $this->stok->catat($item['barang_id'], 'masuk', $data['tanggal'], $item['qty'], $item['harga_beli'],
                    StokMutasi::REF_PEMBELIAN, $pembelian->id, "Pembelian {$pembelian->no_faktur}");
            }

            // Stok, HPP & kartu stok dihitung ulang (menolak jika stok jadi minus)
            $this->stok->hitungUlangBanyak($barangs);

            $pembelian->perbaruiStatusBayar();
            $this->cashflow->syncPembelianStok($pembelian);

            return $pembelian;
        });
    }

    public function hapus(PembelianStok $pembelian): void
    {
        DB::transaction(function () use ($pembelian) {
            if ($pembelian->pembayarans()->exists()) {
                throw new BisnisException("Pembelian {$pembelian->no_faktur} sudah memiliki pembayaran. Hapus pembayarannya dulu.");
            }

            $barangs = $this->stok->kunciBarang($pembelian->details()->pluck('barang_id'));

            $this->stok->hapusReferensi(StokMutasi::REF_PEMBELIAN, $pembelian->id);
            $this->cashflow->hapusPembelianStok($pembelian);
            $pembelian->delete(); // detail ikut terhapus (cascade)

            // Tolak jika barangnya sudah terjual sehingga stok jadi minus
            $this->stok->hitungUlangBanyak($barangs);
        });
    }
}
