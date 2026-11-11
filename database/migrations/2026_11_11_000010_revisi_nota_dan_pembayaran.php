<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 3:
 * 1. Nota penjualan tanpa nomor (dikenali dari klien + tanggal).
 * 2. Belanja supplier dari nota kembali dianggap LUNAS otomatis per hari:
 *    satu baris cashflow keluar "Belanja supplier harian" per tanggal nota.
 *    Pembayaran supplier per tanggal nota (revisi 2) dihapus beserta cashflow-nya, diganti baris harian tsb.
 *    Pembayaran hutang pembelian stok (faktur) tetap ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 2. Belanja supplier lunas otomatis per hari
        $idBayarNota = DB::table('pembayarans')->whereNotNull('tanggal_nota')->pluck('id');
        if ($idBayarNota->isNotEmpty()) {
            DB::table('cashflows')->where('referensi_tipe', 'pembayaran')->whereIn('referensi_id', $idBayarNota)->delete();
            DB::table('pembayarans')->whereIn('id', $idBayarNota)->delete();
        }

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropIndex(['tanggal_nota']);
            $table->dropColumn('tanggal_nota');
        });

        $harian = DB::table('transaksi_details as d')
            ->join('transaksis as t', 't.id', '=', 'd.transaksi_id')
            ->where('d.sumber', 'supplier')
            ->groupBy('t.tanggal')
            ->selectRaw('t.tanggal, SUM(d.subtotal_beli) AS total')
            ->get();

        foreach ($harian as $row) {
            $tanggal = substr((string) $row->tanggal, 0, 10);
            if ((float) $row->total <= 0) {
                continue;
            }
            DB::table('cashflows')->updateOrInsert(
                ['referensi_tipe' => 'belanja_supplier', 'referensi_id' => (int) str_replace('-', '', $tanggal)],
                [
                    'tanggal' => $tanggal,
                    'tipe' => 'keluar',
                    'kategori' => 'belanja_supplier',
                    'nominal' => round((float) $row->total, 2),
                    'keterangan' => 'Belanja supplier harian (nota beli) ' . date('d/m/Y', strtotime($tanggal)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // --- 1. Nota tanpa nomor
        $adaUnique = collect(Schema::getIndexes('transaksis'))->contains(fn ($i) => $i['columns'] === ['no_nota'] && $i['unique']);
        Schema::table('transaksis', function (Blueprint $table) use ($adaUnique) {
            if ($adaUnique) {
                $table->dropUnique(['no_nota']);
            }
            $table->dropColumn('no_nota');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->string('no_nota', 30)->nullable()->unique()->after('id');
        });

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->date('tanggal_nota')->nullable()->after('pembelian_stok_id')->index();
        });

        DB::table('cashflows')->where('referensi_tipe', 'belanja_supplier')->delete();
    }
};
