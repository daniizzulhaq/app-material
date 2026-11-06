<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian_stoks', function (Blueprint $table) {
            $table->id();
            $table->string('no_faktur', 30)->unique();
            $table->date('tanggal')->index();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('metode_bayar', ['tunai', 'hutang']);
            $table->enum('status_bayar', ['belum', 'sebagian', 'lunas'])->default('belum');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('pembelian_stok_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_stok_id')->constrained('pembelian_stoks')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barangs')->restrictOnDelete();
            $table->decimal('qty', 12, 2);
            $table->decimal('harga_beli', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        // Kartu stok: setiap perubahan stok tercatat di sini
        Schema::create('stok_mutasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barangs')->restrictOnDelete();
            $table->date('tanggal');
            $table->enum('tipe', ['masuk', 'keluar', 'penyesuaian']);
            // masuk/keluar selalu positif; penyesuaian bertanda (+/-)
            $table->decimal('qty', 12, 2);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('stok_sebelum', 12, 2)->default(0);
            $table->decimal('stok_sesudah', 12, 2)->default(0);
            $table->string('referensi_tipe', 50)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['barang_id', 'tanggal']);
            $table->index(['referensi_tipe', 'referensi_id']);
        });

        Schema::create('stok_opnames', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->foreignId('barang_id')->constrained('barangs')->restrictOnDelete();
            $table->decimal('stok_sistem', 12, 2);
            $table->decimal('stok_fisik', 12, 2);
            $table->decimal('selisih', 12, 2);
            $table->decimal('nilai_selisih', 15, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_opnames');
        Schema::dropIfExists('stok_mutasis');
        Schema::dropIfExists('pembelian_stok_details');
        Schema::dropIfExists('pembelian_stoks');
    }
};
