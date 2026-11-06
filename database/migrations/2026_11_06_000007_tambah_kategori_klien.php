<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kliens', function (Blueprint $table) {
            // groceries | material — klien lama otomatis masuk "material"
            $table->string('kategori', 20)->default('material')->after('nama')->index();
        });
    }

    public function down(): void
    {
        Schema::table('kliens', function (Blueprint $table) {
            $table->dropIndex(['kategori']);
            $table->dropColumn('kategori');
        });
    }
};
