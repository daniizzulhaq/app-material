<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PembelianStok extends Model
{
    protected $table = 'pembelian_stoks';

    protected $fillable = ['no_faktur', 'tanggal', 'supplier_id', 'total', 'metode_bayar', 'status_bayar', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PembelianStokDetail::class);
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    /** Total yang sudah dibayar (tunai = langsung lunas) */
    public function totalDibayar(): float
    {
        if ($this->metode_bayar === 'tunai') {
            return (float) $this->total;
        }

        return (float) ($this->pembayarans_sum_jumlah ?? $this->pembayarans()->sum('jumlah'));
    }

    public function sisaHutang(): float
    {
        return max(0, round((float) $this->total - $this->totalDibayar(), 2));
    }

    public function perbaruiStatusBayar(): void
    {
        $this->status_bayar = $this->metode_bayar === 'tunai'
            ? 'lunas'
            : status_bayar((float) $this->pembayarans()->sum('jumlah'), (float) $this->total);
        $this->saveQuietly();
    }
}
