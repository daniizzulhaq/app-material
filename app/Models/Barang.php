<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    protected $table = 'barangs';

    public const SATUAN = ['sak', 'm3', 'batang', 'kg', 'pcs', 'dus', 'lembar', 'liter', 'meter', 'roll', 'kaleng', 'truk', 'set'];

    // stok & hpp sengaja tidak fillable: hanya diubah lewat StokService
    protected $fillable = ['nama', 'supplier_id', 'satuan', 'harga_jual_default', 'harga_beli_default', 'stok_minimum'];

    /** Supplier langganan (opsional) */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    protected function casts(): array
    {
        return [
            'harga_jual_default' => 'decimal:2',
            'harga_beli_default' => 'decimal:2',
            'stok' => 'decimal:2',
            'hpp' => 'decimal:2',
            'stok_minimum' => 'decimal:2',
        ];
    }

    public function mutasis(): HasMany
    {
        return $this->hasMany(StokMutasi::class);
    }

    public function transaksiDetails(): HasMany
    {
        return $this->hasMany(TransaksiDetail::class);
    }

    public function pembelianDetails(): HasMany
    {
        return $this->hasMany(PembelianStokDetail::class);
    }

    public function opnames(): HasMany
    {
        return $this->hasMany(StokOpname::class);
    }

    public function getNilaiStokAttribute(): float
    {
        return round((float) $this->stok * (float) $this->hpp, 2);
    }

    public function getMenipisAttribute(): bool
    {
        return (float) $this->stok <= (float) $this->stok_minimum;
    }

    public function scopeMenipis(Builder $query): Builder
    {
        return $query->diStok()->whereColumn('stok', '<=', 'stok_minimum');
    }

    /**
     * Barang yang masuk data stok = pernah tercatat di kartu stok (pembelian stok / stok awal lama).
     * Barang master yang belum pernah dibeli untuk stok tidak tampil di data stok.
     */
    public function scopeDiStok(Builder $query): Builder
    {
        return $query->whereHas('mutasis');
    }

    /** Dipakai = ada transaksi/pembelian/opname, atau mutasi selain stok awal */
    public function sudahDipakai(): bool
    {
        return $this->transaksiDetails()->exists()
            || $this->pembelianDetails()->exists()
            || $this->opnames()->exists()
            || $this->mutasis()->where('referensi_tipe', '!=', StokMutasi::REF_STOK_AWAL)->exists();
    }
}
