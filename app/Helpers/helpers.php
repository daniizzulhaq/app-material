<?php

use Carbon\Carbon;

if (! function_exists('rupiah')) {
    /**
     * Format angka ke Rupiah: Rp 9.965.000
     */
    function rupiah($angka, bool $denganPrefix = true): string
    {
        $angka = (float) ($angka ?? 0);
        $negatif = $angka < 0;
        $hasil = number_format(abs($angka), 0, ',', '.');

        return ($negatif ? '-' : '') . ($denganPrefix ? 'Rp ' : '') . $hasil;
    }
}

if (! function_exists('qty')) {
    /**
     * Format qty decimal(12,2): 10 -> "10", 2.5 -> "2,5", 1.25 -> "1,25"
     */
    function qty($angka): string
    {
        $angka = (float) ($angka ?? 0);
        $hasil = number_format($angka, 2, ',', '.');

        // Buang ",00" / nol di belakang agar tampil ringkas
        return rtrim(rtrim($hasil, '0'), ',');
    }
}

if (! function_exists('status_bayar')) {
    /**
     * Tentukan status bayar dari jumlah dibayar vs total tagihan.
     */
    function status_bayar(float $dibayar, float $total): string
    {
        if ($dibayar <= 0 && $total > 0) {
            return 'belum';
        }

        return round($dibayar, 2) >= round($total, 2) ? 'lunas' : 'sebagian';
    }
}

if (! function_exists('tanggal')) {
    /**
     * Format tanggal: 06 Nov 2026
     */
    function tanggal($tanggal, string $format = 'd M Y'): string
    {
        if (empty($tanggal)) {
            return '-';
        }

        return Carbon::parse($tanggal)->locale('id')->translatedFormat($format);
    }
}
