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

if (! function_exists('input_uang')) {
    /**
     * Nilai awal <input class="input-uang">: 64000.00 -> "64.000" (kosong bila null/'').
     */
    function input_uang($angka): string
    {
        if ($angka === null || $angka === '') {
            return '';
        }

        return number_format((float) $angka, 0, ',', '.');
    }
}

if (! function_exists('input_qty')) {
    /**
     * Nilai awal input qty: 2.50 -> "2,5", 1000 -> "1000" (tanpa titik ribuan agar tidak terbaca 1).
     */
    function input_qty($angka): string
    {
        if ($angka === null || $angka === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $angka, 2, ',', ''), '0'), ',');
    }
}

if (! function_exists('ids')) {
    /**
     * Daftar ID dari query string: terima ?klien_id=3 maupun ?klien_id[]=3&klien_id[]=5.
     *
     * @return int[]
     */
    function ids(mixed $nilai): array
    {
        return collect((array) $nilai)
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()->values()->all();
    }
}

if (! function_exists('baca_uang')) {
    /**
     * Teks uang dari form -> angka murni: "Rp 9.965.000" -> "9965000", "1.500,5" -> "1500.5".
     * Nilai non-teks / kosong dikembalikan apa adanya.
     */
    function baca_uang(mixed $nilai): mixed
    {
        if (! is_string($nilai) || trim($nilai) === '') {
            return $nilai;
        }

        return str_replace(',', '.', str_replace(['Rp', ' ', '.'], '', $nilai));
    }
}

if (! function_exists('baca_qty')) {
    /**
     * Teks qty dari form -> angka murni (sama dengan parseQty() di app.js):
     *  "2,5" -> "2.5", "1.000,5" -> "1000.5", "1.000.000" -> "1000000", "2.5" -> "2.5".
     */
    function baca_qty(mixed $nilai): mixed
    {
        if (! is_string($nilai)) {
            return $nilai;
        }
        $nilai = str_replace(' ', '', trim($nilai));

        if (str_contains($nilai, ',')) {
            return str_replace(',', '.', str_replace('.', '', $nilai));
        }

        return substr_count($nilai, '.') > 1 ? str_replace('.', '', $nilai) : $nilai;
    }
}
