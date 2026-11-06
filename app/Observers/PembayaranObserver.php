<?php

namespace App\Observers;

use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Transaksi;
use App\Services\CashflowService;

/**
 * Menjaga cashflow & status bayar selalu sinkron dengan data pembayaran.
 * Dijalankan di dalam DB::transaction() milik controller, sehingga ikut di-rollback bila gagal.
 */
class PembayaranObserver
{
    public function __construct(private CashflowService $cashflow)
    {
    }

    public function saved(Pembayaran $pembayaran): void
    {
        $this->cashflow->syncPembayaran($pembayaran);

        $this->perbaruiStatus($pembayaran->transaksi_id, $pembayaran->pembelian_stok_id);

        // Jika tagihan yang dibayar diganti, perbarui juga status tagihan lama
        if ($pembayaran->wasChanged(['transaksi_id', 'pembelian_stok_id'])) {
            $this->perbaruiStatus($pembayaran->getOriginal('transaksi_id'), $pembayaran->getOriginal('pembelian_stok_id'));
        }
    }

    public function deleted(Pembayaran $pembayaran): void
    {
        $this->cashflow->hapusOtomatis(CashflowService::REF_PEMBAYARAN, $pembayaran->id);
        $this->perbaruiStatus($pembayaran->transaksi_id, $pembayaran->pembelian_stok_id);
    }

    private function perbaruiStatus(?int $transaksiId, ?int $pembelianStokId): void
    {
        if ($transaksiId) {
            Transaksi::find($transaksiId)?->perbaruiStatusBayar();
        }
        if ($pembelianStokId) {
            PembelianStok::find($pembelianStokId)?->perbaruiStatusBayar();
        }
    }
}
