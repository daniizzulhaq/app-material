<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('no_hp', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('kliens', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('no_hp', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('barangs', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->string('satuan', 20);
            $table->decimal('harga_jual_default', 15, 2)->default(0);
            // stok & hpp hanya diubah oleh StokService
            $table->decimal('stok', 12, 2)->default(0);
            $table->decimal('hpp', 15, 2)->default(0);
            $table->decimal('stok_minimum', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangs');
        Schema::dropIfExists('kliens');
        Schema::dropIfExists('suppliers');
    }
};
