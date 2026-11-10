<?php

namespace App\Services;

use App\Models\Cashflow;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use Carbon\Carbon;

/**
 * Sinkronisasi baris cashflow otomatis.
 * CASHFLOW = kapan uang benar-benar keluar/masuk (terpisah dari perhitungan laba).
 * Baris otomatis dikenali dari (referensi_tipe, referensi_id) yang unik.
 */
class CashflowService
{
    public const REF_PEMBELIAN = 'pembelian_stok';
    public const REF_PEMBAYARAN = 'pembayaran';

    public function simpanOtomatis(string $referensiTipe, int $referensiId, array $data): Cashflow
    {
        return Cashflow::updateOrCreate(
            ['referensi_tipe' => $referensiTipe, 'referensi_id' => $referensiId],
            $data
        );
    }

    public function hapusOtomatis(string $referensiTipe, int $referensiId): void
    {
        Cashflow::where('referensi_tipe', $referensiTipe)->where('referensi_id', $referensiId)->delete();
    }

    /** Pembelian tunai → uang keluar saat itu. Hutang → keluar saat dibayar (lewat Pembayaran). */
    public function syncPembelianStok(PembelianStok $pembelian): void
    {
        if ($pembelian->metode_bayar !== 'tunai') {
            $this->hapusOtomatis(self::REF_PEMBELIAN, $pembelian->id);

            return;
        }

        $pembelian->loadMissing('supplier');

        $this->simpanOtomatis(self::REF_PEMBELIAN, $pembelian->id, [
            'tanggal' => $pembelian->tanggal,
            'tipe' => 'keluar',
            'kategori' => 'pembelian_stok',
            'nominal' => $pembelian->total,
            'keterangan' => "Pembelian stok {$pembelian->no_faktur} - {$pembelian->supplier->nama} (tunai)",
        ]);
    }

    public function hapusPembelianStok(PembelianStok $pembelian): void
    {
        $this->hapusOtomatis(self::REF_PEMBELIAN, $pembelian->id);
    }

    /** Dipanggil oleh PembayaranObserver */
    public function syncPembayaran(Pembayaran $pembayaran): void
    {
        $pembayaran->loadMissing(['transaksi', 'pembelianStok', 'klien', 'supplier']);
        $klien = $pembayaran->jenis === 'klien';

        $dokumen = $klien
            ? $pembayaran->transaksi?->no_nota
            : ($pembayaran->tanggal_nota ? 'nota ' . $pembayaran->tanggal_nota->format('d/m/Y') : $pembayaran->pembelianStok?->no_faktur);
        $nama = $klien ? $pembayaran->klien?->nama : $pembayaran->supplier?->nama;

        $this->simpanOtomatis(self::REF_PEMBAYARAN, $pembayaran->id, [
            'tanggal' => $pembayaran->tanggal,
            'tipe' => $klien ? 'masuk' : 'keluar',
            'kategori' => $klien ? 'pembayaran_klien' : 'pembayaran_supplier',
            'nominal' => $pembayaran->jumlah,
            'keterangan' => trim(($klien ? 'Pembayaran dari ' : 'Pembayaran ke ') . "{$nama} ({$dokumen})"),
        ]);
    }

    /** Saldo kas sebelum tanggal tertentu (untuk saldo awal periode) */
    public function saldoSebelum($tanggal): float
    {
        $row = Cashflow::query()
            ->whereDate('tanggal', '<', Carbon::parse($tanggal)->toDateString())
            ->selectRaw("COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE -nominal END), 0) AS saldo")
            ->first();

        return (float) $row->saldo;
    }
}
