<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            // Supplier langganan barang ini (opsional). Otomatis terisi saat barang dipilih di nota.
            $table->foreignId('supplier_id')->nullable()->after('nama')->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
