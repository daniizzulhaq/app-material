<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Klien extends Model
{
    protected $table = 'kliens';

    /** Jenis rekap klien */
    public const KATEGORI = [
        'groceries' => ['label' => 'Groceries', 'icon' => 'bi-basket2', 'warna' => 'success'],
        'material' => ['label' => 'Material', 'icon' => 'bi-bricks', 'warna' => 'warning'],
    ];

    protected $fillable = ['nama', 'kategori', 'no_hp', 'alamat', 'keterangan'];

    public function scopeKategori($query, ?string $kategori)
    {
        return $query->when(array_key_exists((string) $kategori, self::KATEGORI), fn ($q) => $q->where('kliens.kategori', $kategori));
    }

    public function getNamaKategoriAttribute(): string
    {
        return self::KATEGORI[$this->kategori]['label'] ?? '-';
    }

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
