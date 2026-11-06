<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Nomor dokumen otomatis per bulan: PB-202611-0001, NJ-202611-0001.
 * Dipanggil di dalam transaksi DB; kolom nomor juga unique di database.
 */
class NomorService
{
    /** @param class-string<Model> $model */
    public function buat(string $model, string $kolom, string $kode, $tanggal): string
    {
        $prefix = $kode . '-' . Carbon::parse($tanggal)->format('Ym') . '-';

        $terakhir = $model::query()
            ->where($kolom, 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc($kolom)
            ->value($kolom);

        $urut = $terakhir ? ((int) substr($terakhir, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }
}
