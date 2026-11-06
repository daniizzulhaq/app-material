<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiDetail extends Model
{
    protected $table = 'transaksi_details';

    protected $fillable = [
        'transaksi_id', 'barang_id', 'sumber', 'supplier_id', 'qty',
        'harga_beli', 'harga_jual', 'subtotal_beli', 'subtotal_jual',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga_beli' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'subtotal_beli' => 'decimal:2',
            'subtotal_jual' => 'decimal:2',
        ];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
