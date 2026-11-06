<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Cashflow;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Services\PembelianStokService;
use App\Services\TransaksiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Contoh transaksi bulan berjalan. Dibuat lewat Service yang sama dengan aplikasi,
 * sehingga stok, HPP, mutasi, dan cashflow terbentuk dengan benar.
 */
class DemoTransaksiSeeder extends Seeder
{
    public function run(PembelianStokService $pembelian, TransaksiService $nota): void
    {
        if (Transaksi::exists()) {
            return; // jangan menggandakan data demo
        }

        $s = Supplier::pluck('id', 'nama');
        $b = Barang::pluck('id', 'nama');
        $k = Klien::pluck('id', 'nama');
        $hari = fn (int $n) => now()->startOfMonth()->addDays(min($n, now()->day - 1))->toDateString();

        // Modal awal
        Cashflow::create(['tanggal' => $hari(0), 'tipe' => 'masuk', 'kategori' => 'modal', 'nominal' => 50000000, 'keterangan' => 'Setoran modal awal']);

        // Pembelian stok gudang
        $pembelian->simpan(['tanggal' => $hari(0), 'supplier_id' => $s['UD Sumber Makmur'], 'metode_bayar' => 'tunai', 'items' => [
            ['barang_id' => $b['Semen Gresik 40kg'], 'qty' => 100, 'harga_beli' => 60000],
            ['barang_id' => $b['Bata Merah'], 'qty' => 5000, 'harga_beli' => 700],
        ]]);
        $pb2 = $pembelian->simpan(['tanggal' => $hari(1), 'supplier_id' => $s['Toko Besi Sentosa'], 'metode_bayar' => 'hutang', 'items' => [
            ['barang_id' => $b['Besi Beton 8mm'], 'qty' => 200, 'harga_beli' => 45000],
            ['barang_id' => $b['Besi Beton 10mm'], 'qty' => 100, 'harga_beli' => 72000],
            ['barang_id' => $b['Kawat Bendrat'], 'qty' => 50, 'harga_beli' => 21000],
        ]]);
        // Dibuat berurutan sesuai tanggal: HPP item stok terkunci = HPP saat nota diinput

        // Nota penjualan campuran (supplier & stok)
        $n1 = $nota->simpan(['tanggal' => $hari(2), 'klien_id' => $k['Pak Budi (Proyek Ruko)'], 'keterangan' => 'Kirim ke proyek', 'items' => [
            ['barang_id' => $b['Semen Gresik 40kg'], 'sumber' => 'stok', 'qty' => 40, 'harga_jual' => 68000],
            ['barang_id' => $b['Pasir Beton'], 'sumber' => 'supplier', 'supplier_id' => $s['CV Pasir Jaya'], 'qty' => 4, 'harga_beli' => 260000, 'harga_jual' => 320000],
            ['barang_id' => $b['Besi Beton 8mm'], 'sumber' => 'stok', 'qty' => 60, 'harga_jual' => 52000],
        ]]);

        // Pembelian kedua semen dengan harga berbeda → HPP rata-rata tertimbang
        $pembelian->simpan(['tanggal' => $hari(3), 'supplier_id' => $s['UD Sumber Makmur'], 'metode_bayar' => 'tunai', 'items' => [
            ['barang_id' => $b['Semen Gresik 40kg'], 'qty' => 50, 'harga_beli' => 63000],
            ['barang_id' => $b['Cat Tembok Dulux 5kg'], 'qty' => 10, 'harga_beli' => 210000],
        ]]);
        $n2 = $nota->simpan(['tanggal' => $hari(4), 'klien_id' => $k['CV Bangun Karya'], 'items' => [
            ['barang_id' => $b['Bata Ringan (Hebel)'], 'sumber' => 'supplier', 'supplier_id' => $s['UD Sumber Makmur'], 'qty' => 500, 'harga_beli' => 8000, 'harga_jual' => 9500],
            ['barang_id' => $b['Bata Merah'], 'sumber' => 'stok', 'qty' => 3000, 'harga_jual' => 900],
            ['barang_id' => $b['Batu Split 2/3'], 'sumber' => 'supplier', 'supplier_id' => $s['CV Pasir Jaya'], 'qty' => 3, 'harga_beli' => 290000, 'harga_jual' => 350000],
        ]]);
        $nota->simpan(['tanggal' => $hari(4), 'klien_id' => $k['Ibu Sari'], 'items' => [
            ['barang_id' => $b['Cat Tembok Dulux 5kg'], 'sumber' => 'stok', 'qty' => 6, 'harga_jual' => 245000],
            ['barang_id' => $b['Keramik 40x40'], 'sumber' => 'supplier', 'supplier_id' => $s['PT Warna Indah'], 'qty' => 10, 'harga_beli' => 52000, 'harga_jual' => 62000],
        ]]);
        $nota->simpan(['tanggal' => $hari(6), 'klien_id' => $k['Mandor Joko'], 'items' => [
            ['barang_id' => $b['Semen Gresik 40kg'], 'sumber' => 'stok', 'qty' => 95, 'harga_jual' => 67000],
            ['barang_id' => $b['Besi Beton 10mm'], 'sumber' => 'stok', 'qty' => 80, 'harga_jual' => 82000],
            ['barang_id' => $b['Pasir Pasang'], 'sumber' => 'supplier', 'supplier_id' => $s['CV Pasir Jaya'], 'qty' => 2, 'harga_beli' => 230000, 'harga_jual' => 280000],
        ]]);

        // Pembayaran (cashflow & status bayar terisi otomatis via observer)
        DB::transaction(function () use ($n1, $n2, $pb2, $hari) {
            Pembayaran::create(['tanggal' => $hari(2), 'jenis' => 'klien', 'klien_id' => $n1->klien_id, 'transaksi_id' => $n1->id, 'jumlah' => $n1->total_jual, 'metode' => 'transfer', 'keterangan' => 'Lunas']);
            Pembayaran::create(['tanggal' => $hari(5), 'jenis' => 'klien', 'klien_id' => $n2->klien_id, 'transaksi_id' => $n2->id, 'jumlah' => 3000000, 'metode' => 'tunai', 'keterangan' => 'DP']);
            Pembayaran::create(['tanggal' => $hari(5), 'jenis' => 'supplier', 'supplier_id' => $pb2->supplier_id, 'pembelian_stok_id' => $pb2->id, 'jumlah' => 10000000, 'metode' => 'transfer', 'keterangan' => 'Cicilan 1']);
        });

        Cashflow::create(['tanggal' => $hari(6), 'tipe' => 'keluar', 'kategori' => 'operasional', 'nominal' => 350000, 'keterangan' => 'Solar truk pengiriman']);
    }
}
