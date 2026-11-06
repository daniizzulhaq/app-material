<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = ['nama', 'no_hp', 'alamat', 'keterangan'];

    public function barangs(): HasMany
    {
        return $this->hasMany(Barang::class);
    }

    public function pembelianStoks(): HasMany
    {
        return $this->hasMany(PembelianStok::class);
    }

    public function transaksiDetails(): HasMany
    {
        return $this->hasMany(TransaksiDetail::class);
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    /** Supplier yang sudah dipakai di transaksi tidak boleh dihapus */
    public function sudahDipakai(): bool
    {
        return $this->pembelianStoks()->exists()
            || $this->transaksiDetails()->exists()
            || $this->pembayarans()->exists();
    }
}
