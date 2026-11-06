<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_hapus', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30)->index();     // supplier, klien, barang, pembelian_stok, transaksi, stok_opname, pembayaran, cashflow
            $table->string('label', 200);              // nama / no. dokumen yang dihapus
            $table->string('ringkasan', 255)->nullable();
            $table->json('data');                      // snapshot untuk dipulihkan
            $table->foreignId('dihapus_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dipulihkan_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_hapus');
    }
};
