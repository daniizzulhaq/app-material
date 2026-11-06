<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokMutasi extends Model
{
    protected $table = 'stok_mutasis';

    public const REF_STOK_AWAL = 'stok_awal';
    public const REF_PEMBELIAN = 'pembelian_stok';
    public const REF_TRANSAKSI = 'transaksi';
    public const REF_OPNAME = 'stok_opname';

    protected $fillable = [
        'barang_id', 'tanggal', 'tipe', 'qty', 'harga', 'stok_sebelum', 'stok_sesudah',
        'referensi_tipe', 'referensi_id', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'qty' => 'decimal:2',
            'harga' => 'decimal:2',
            'stok_sebelum' => 'decimal:2',
            'stok_sesudah' => 'decimal:2',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    /** Qty bertanda: masuk (+), keluar (−), penyesuaian (apa adanya) */
    public function getQtyBertandaAttribute(): float
    {
        return $this->tipe === 'keluar' ? -(float) $this->qty : (float) $this->qty;
    }

    /** Link ke dokumen sumber mutasi (untuk kartu stok) */
    public function getReferensiUrlAttribute(): ?string
    {
        return match ($this->referensi_tipe) {
            self::REF_PEMBELIAN => route('pembelian-stok.show', $this->referensi_id),
            self::REF_TRANSAKSI => route('transaksi.show', $this->referensi_id),
            self::REF_OPNAME => route('stok-opname.index'),
            default => null,
        };
    }

    public function getReferensiLabelAttribute(): string
    {
        return match ($this->referensi_tipe) {
            self::REF_STOK_AWAL => 'Stok Awal',
            self::REF_PEMBELIAN => 'Pembelian Stok',
            self::REF_TRANSAKSI => 'Nota Penjualan',
            self::REF_OPNAME => 'Stok Opname',
            default => '-',
        };
    }
}
