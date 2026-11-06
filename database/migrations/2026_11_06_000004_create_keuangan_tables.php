<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayarans', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->enum('jenis', ['klien', 'supplier']);
            $table->foreignId('klien_id')->nullable()->constrained('kliens')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksis')->restrictOnDelete();
            $table->foreignId('pembelian_stok_id')->nullable()->constrained('pembelian_stoks')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->enum('metode', ['tunai', 'transfer']);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('cashflows', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->enum('tipe', ['masuk', 'keluar']);
            $table->enum('kategori', [
                'pembelian_stok', 'belanja_supplier', 'pembayaran_klien',
                'pembayaran_supplier', 'operasional', 'modal', 'lainnya',
            ]);
            $table->decimal('nominal', 15, 2);
            // Terisi = baris otomatis. Belanja supplier harian memakai referensi_id = Ymd (mis. 20261106)
            $table->string('referensi_tipe', 50)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['referensi_tipe', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflows');
        Schema::dropIfExists('pembayarans');
    }
};
