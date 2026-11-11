<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Opsi tampilan laporan (sama seperti web sayur): format qty, mode harga, tampilkan/sembunyikan klien.
 */
class OpsiLaporan
{
    /**
     * Barang yang sama dipesan beberapa kali di tanggal yang sama (mis. pesanan awal + tambahan):
     *  - total   : dijumlah            -> "2 sak"
     *  - rincian : dirinci per pesanan -> "1 + 1 sak"
     */
    public const FORMAT_QTY = [
        'total' => 'Total',
        'rincian' => '1 + 1',
    ];

    public const MODE_HARGA = [
        'lengkap' => 'Lengkap',
        'jual' => 'Jual saja',
        'beli' => 'Beli saja',
        'tanpa' => 'Tanpa harga',
    ];

    public function __construct(
        public string $formatQty = 'total',
        public string $modeHarga = 'lengkap',
        public bool $tampilKlien = true,
    ) {
    }

    public static function dariRequest(Request $request, string $modeDefault = 'lengkap', bool $klienDefault = true): self
    {
        $format = (string) $request->input('qty', 'total');
        $mode = (string) $request->input('harga', $modeDefault);
        $klien = $request->input('klien');

        return new self(
            array_key_exists($format, self::FORMAT_QTY) ? $format : 'total',
            array_key_exists($mode, self::MODE_HARGA) ? $mode : $modeDefault,
            in_array($klien, ['tampil', 'sembunyi'], true) ? $klien === 'tampil' : $klienDefault,
        );
    }

    public function beli(): bool
    {
        return in_array($this->modeHarga, ['lengkap', 'beli'], true);
    }

    public function jual(): bool
    {
        return in_array($this->modeHarga, ['lengkap', 'jual'], true);
    }

    public function laba(): bool
    {
        return $this->modeHarga === 'lengkap';
    }

    /** Mode "Tanpa harga": hanya barang & qty (mis. untuk surat jalan / cek barang). */
    public function adaHarga(): bool
    {
        return $this->modeHarga !== 'tanpa';
    }

    public function rinci(): bool
    {
        return $this->formatQty === 'rincian';
    }

    /**
     * Format qty. $pecahan = qty tiap pesanan bila barang dipesan lebih dari sekali di tanggal yang sama.
     * Excel (format Total): angka mentah agar bisa dihitung; satuan di kolom tersendiri.
     */
    public function qty(float|string|null $nilai, ?string $satuan, bool $excel, array $pecahan = []): string|float
    {
        $satuan = $excel || ! $satuan ? '' : ' ' . $satuan;

        if ($this->rinci() && count($pecahan) > 1) {
            return implode(' + ', array_map(fn ($v) => qty($v), $pecahan)) . $satuan; // "6 + 2 sak"
        }

        return $excel ? round((float) $nilai, 2) : qty($nilai) . $satuan;
    }
}
