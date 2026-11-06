<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cashflow extends Model
{
    protected $table = 'cashflows';

    public const KATEGORI = [
        'pembelian_stok' => 'Pembelian Stok',
        'belanja_supplier' => 'Belanja Supplier',
        'pembayaran_klien' => 'Pembayaran Klien',
        'pembayaran_supplier' => 'Pembayaran Supplier',
        'operasional' => 'Operasional',
        'modal' => 'Modal',
        'lainnya' => 'Lainnya',
    ];

    /** Kategori yang boleh diinput manual */
    public const KATEGORI_MANUAL = ['operasional', 'modal', 'lainnya'];

    protected $fillable = ['tanggal', 'tipe', 'kategori', 'nominal', 'referensi_tipe', 'referensi_id', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'nominal' => 'decimal:2',
        ];
    }

    /** Baris otomatis = dibuat sistem, tidak bisa diedit manual */
    public function getOtomatisAttribute(): bool
    {
        return ! is_null($this->referensi_tipe);
    }

    public function getKategoriLabelAttribute(): string
    {
        return self::KATEGORI[$this->kategori] ?? $this->kategori;
    }
}
