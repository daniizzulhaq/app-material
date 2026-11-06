<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembelianStokDetail extends Model
{
    protected $table = 'pembelian_stok_details';

    protected $fillable = ['pembelian_stok_id', 'barang_id', 'qty', 'harga_beli', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga_beli' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pembelianStok(): BelongsTo
    {
        return $this->belongsTo(PembelianStok::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }
}
