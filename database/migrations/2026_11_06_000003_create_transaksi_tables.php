<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nota penjualan
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();
            $table->string('no_nota', 30)->unique();
            $table->date('tanggal')->index();
            $table->foreignId('klien_id')->constrained('kliens')->restrictOnDelete();
            $table->decimal('total_qty', 12, 2)->default(0);
            $table->decimal('total_jual', 15, 2)->default(0);
            $table->decimal('total_modal', 15, 2)->default(0);
            $table->decimal('total_belanja_supplier', 15, 2)->default(0);
            $table->decimal('laba', 15, 2)->default(0);
            $table->enum('status_bayar', ['belum', 'sebagian', 'lunas'])->default('belum');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('transaksi_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksis')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barangs')->restrictOnDelete();
            $table->enum('sumber', ['supplier', 'stok']);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->decimal('qty', 12, 2);
            $table->decimal('harga_beli', 15, 2);
            $table->decimal('harga_jual', 15, 2);
            $table->decimal('subtotal_beli', 15, 2);
            $table->decimal('subtotal_jual', 15, 2);
            $table->timestamps();

            $table->index(['sumber', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_details');
        Schema::dropIfExists('transaksis');
    }
};
