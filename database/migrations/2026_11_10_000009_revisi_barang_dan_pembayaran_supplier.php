<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi:
 * 1. Barang punya harga beli default (dipakai otomatis di nota sumber supplier).
 * 2. Belanja supplier dari nota tidak lagi dianggap lunas di hari itu, tetapi dibayar lewat
 *    menu Pembayaran per supplier per tanggal nota (kolom tanggal_nota).
 *    Data lama: baris cashflow "Belanja supplier harian" diubah menjadi pembayaran supplier
 *    per supplier per tanggal (lunas), sehingga saldo kas & laporan tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            $table->decimal('harga_beli_default', 15, 2)->default(0)->after('satuan');
        });

        Schema::table('pembayarans', function (Blueprint $table) {
            // Pembayaran supplier atas belanja nota (sumber supplier) pada tanggal ini
            $table->date('tanggal_nota')->nullable()->after('pembelian_stok_id')->index();
        });

        $this->konversiBelanjaHarian();
    }

    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropIndex(['tanggal_nota']);
            $table->dropColumn('tanggal_nota');
        });

        Schema::table('barangs', function (Blueprint $table) {
            $table->dropColumn('harga_beli_default');
        });
    }

    private function konversiBelanjaHarian(): void
    {
        $belanja = DB::table('cashflows')->where('referensi_tipe', 'belanja_supplier')->get();

        foreach ($belanja as $cf) {
            $tanggal = substr((string) $cf->tanggal, 0, 10);

            $perSupplier = DB::table('transaksi_details as d')
                ->join('transaksis as t', 't.id', '=', 'd.transaksi_id')
                ->where('d.sumber', 'supplier')
                ->whereDate('t.tanggal', $tanggal)
                ->groupBy('d.supplier_id')
                ->selectRaw('d.supplier_id, SUM(d.subtotal_beli) AS total')
                ->get();

            foreach ($perSupplier as $row) {
                if ((float) $row->total <= 0 || ! $row->supplier_id) {
                    continue;
                }

                $nama = DB::table('suppliers')->where('id', $row->supplier_id)->value('nama');
                $id = DB::table('pembayarans')->insertGetId([
                    'tanggal' => $tanggal,
                    'jenis' => 'supplier',
                    'supplier_id' => $row->supplier_id,
                    'tanggal_nota' => $tanggal,
                    'jumlah' => round((float) $row->total, 2),
                    'metode' => 'tunai',
                    'keterangan' => 'Otomatis dari belanja supplier harian (data lama)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('cashflows')->insert([
                    'tanggal' => $tanggal,
                    'tipe' => 'keluar',
                    'kategori' => 'pembayaran_supplier',
                    'nominal' => round((float) $row->total, 2),
                    'referensi_tipe' => 'pembayaran',
                    'referensi_id' => $id,
                    'keterangan' => "Pembayaran ke {$nama} (nota " . date('d/m/Y', strtotime($tanggal)) . ')',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('cashflows')->where('id', $cf->id)->delete();
        }
    }
};
