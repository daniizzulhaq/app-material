<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota penjualan */
class Transaksi extends Model
{
    protected $table = 'transaksis';

    protected $fillable = [
        'no_nota', 'tanggal', 'klien_id', 'total_qty', 'total_jual', 'total_modal',
        'total_belanja_supplier', 'laba', 'status_bayar', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'total_qty' => 'decimal:2',
            'total_jual' => 'decimal:2',
            'total_modal' => 'decimal:2',
            'total_belanja_supplier' => 'decimal:2',
            'laba' => 'decimal:2',
        ];
    }

    public function klien(): BelongsTo
    {
        return $this->belongsTo(Klien::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransaksiDetail::class);
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function totalDibayar(): float
    {
        return (float) ($this->pembayarans_sum_jumlah ?? $this->pembayarans()->sum('jumlah'));
    }

    public function sisaPiutang(): float
    {
        return max(0, round((float) $this->total_jual - $this->totalDibayar(), 2));
    }

    public function perbaruiStatusBayar(): void
    {
        $this->status_bayar = status_bayar((float) $this->pembayarans()->sum('jumlah'), (float) $this->total_jual);
        $this->saveQuietly();
    }
}
