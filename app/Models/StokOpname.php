<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpname extends Model
{
    protected $table = 'stok_opnames';

    protected $fillable = ['tanggal', 'barang_id', 'stok_sistem', 'stok_fisik', 'selisih', 'nilai_selisih', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'stok_sistem' => 'decimal:2',
            'stok_fisik' => 'decimal:2',
            'selisih' => 'decimal:2',
            'nilai_selisih' => 'decimal:2',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }
}
