<?php

namespace App\Models;

use App\Observers\PembayaranObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cashflow & status bayar disinkronkan otomatis oleh PembayaranObserver */
#[ObservedBy(PembayaranObserver::class)]
class Pembayaran extends Model
{
    protected $table = 'pembayarans';

    protected $fillable = [
        'tanggal', 'jenis', 'klien_id', 'supplier_id', 'transaksi_id', 'pembelian_stok_id',
        'jumlah', 'metode', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah' => 'decimal:2',
        ];
    }

    public function klien(): BelongsTo
    {
        return $this->belongsTo(Klien::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function pembelianStok(): BelongsTo
    {
        return $this->belongsTo(PembelianStok::class);
    }
}
