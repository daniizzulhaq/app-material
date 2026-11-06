<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Klien extends Model
{
    protected $table = 'kliens';

    protected $fillable = ['nama', 'no_hp', 'alamat', 'keterangan'];

    public function transaksis(): HasMany
    {
        return $this->hasMany(Transaksi::class);
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function sudahDipakai(): bool
    {
        return $this->transaksis()->exists() || $this->pembayarans()->exists();
    }
}
