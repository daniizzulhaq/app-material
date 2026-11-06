<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (stok minus, data sudah dipakai, dll).
 * Otomatis ditampilkan sebagai toast error & form kembali dengan input lama
 * (lihat bootstrap/app.php). Karena dilempar di dalam DB::transaction(),
 * semua perubahan otomatis di-rollback.
 */
class BisnisException extends RuntimeException
{
}
