<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatHapus extends Model
{
    protected $table = 'riwayat_hapus';

    public const JENIS = [
        'supplier' => ['label' => 'Supplier', 'icon' => 'bi-truck'],
        'klien' => ['label' => 'Klien', 'icon' => 'bi-people'],
        'barang' => ['label' => 'Barang', 'icon' => 'bi-box-seam'],
        'pembelian_stok' => ['label' => 'Pembelian Stok', 'icon' => 'bi-cart-plus'],
        'transaksi' => ['label' => 'Nota', 'icon' => 'bi-receipt'],
        'stok_opname' => ['label' => 'Stok Opname', 'icon' => 'bi-clipboard-check'],
        'pembayaran' => ['label' => 'Pembayaran', 'icon' => 'bi-credit-card'],
        'cashflow' => ['label' => 'Cashflow', 'icon' => 'bi-cash-stack'],
    ];

    protected $fillable = ['jenis', 'label', 'ringkasan', 'data', 'dihapus_oleh', 'dipulihkan_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'dipulihkan_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihapus_oleh');
    }

    public function getNamaJenisAttribute(): string
    {
        return self::JENIS[$this->jenis]['label'] ?? $this->jenis;
    }

    public function getIconAttribute(): string
    {
        return self::JENIS[$this->jenis]['icon'] ?? 'bi-trash';
    }
}
