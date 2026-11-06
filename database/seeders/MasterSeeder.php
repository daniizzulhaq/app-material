<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Klien;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/** Data dummy master: supplier, barang material, klien */
class MasterSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['nama' => 'UD Sumber Makmur', 'no_hp' => '081234567801', 'alamat' => 'Jl. Raya Industri No. 12', 'keterangan' => 'Semen & bata'],
            ['nama' => 'CV Pasir Jaya', 'no_hp' => '081234567802', 'alamat' => 'Jl. Kali Progo Km 5', 'keterangan' => 'Pasir, batu, split'],
            ['nama' => 'Toko Besi Sentosa', 'no_hp' => '081234567803', 'alamat' => 'Jl. Pahlawan No. 88', 'keterangan' => 'Besi beton & wiremesh'],
            ['nama' => 'PT Warna Indah', 'no_hp' => '081234567804', 'alamat' => 'Kawasan Niaga Blok C-3', 'keterangan' => 'Distributor cat'],
            ['nama' => 'UD Kayu Lestari', 'no_hp' => '081234567805', 'alamat' => 'Jl. Hutan Jati No. 7', 'keterangan' => 'Kayu & triplek'],
        ];
        foreach ($suppliers as $s) {
            Supplier::updateOrCreate(['nama' => $s['nama']], $s);
        }

        // [nama, satuan, harga_jual_default, stok_minimum]
        $barangs = [
            ['Semen Gresik 40kg', 'sak', 68000, 20],
            ['Semen Tiga Roda 50kg', 'sak', 78000, 20],
            ['Pasir Beton', 'm3', 320000, 3],
            ['Pasir Pasang', 'm3', 280000, 3],
            ['Batu Split 2/3', 'm3', 350000, 2],
            ['Besi Beton 8mm', 'batang', 52000, 50],
            ['Besi Beton 10mm', 'batang', 82000, 30],
            ['Besi Beton 12mm', 'batang', 118000, 20],
            ['Bata Merah', 'pcs', 900, 1000],
            ['Bata Ringan (Hebel)', 'pcs', 9500, 200],
            ['Cat Tembok Dulux 5kg', 'kaleng', 245000, 5],
            ['Cat Avitex 5kg', 'kaleng', 135000, 5],
            ['Paku 5cm', 'kg', 24000, 10],
            ['Kawat Bendrat', 'kg', 26000, 10],
            ['Triplek 9mm', 'lembar', 125000, 10],
            ['Pipa PVC 3"', 'batang', 85000, 10],
            ['Keramik 40x40', 'dus', 62000, 15],
            ['Genteng Metal', 'lembar', 48000, 30],
        ];
        foreach ($barangs as [$nama, $satuan, $harga, $min]) {
            Barang::updateOrCreate(['nama' => $nama], [
                'satuan' => $satuan,
                'harga_jual_default' => $harga,
                'stok_minimum' => $min,
            ]);
        }

        $kliens = [
            ['nama' => 'Pak Budi (Proyek Ruko)', 'no_hp' => '085711110001', 'alamat' => 'Jl. Melati No. 3'],
            ['nama' => 'CV Bangun Karya', 'no_hp' => '085711110002', 'alamat' => 'Jl. Anggrek Raya 21', 'keterangan' => 'Kontraktor'],
            ['nama' => 'Ibu Sari', 'no_hp' => '085711110003', 'alamat' => 'Perum Griya Asri B-10'],
            ['nama' => 'Mandor Joko', 'no_hp' => '085711110004', 'alamat' => 'Dusun Sukamaju RT 02'],
            ['nama' => 'PT Graha Properti', 'no_hp' => '085711110005', 'alamat' => 'Jl. Sudirman Kav. 9', 'keterangan' => 'Developer perumahan'],
        ];
        foreach ($kliens as $k) {
            Klien::updateOrCreate(['nama' => $k['nama']], $k);
        }
    }
}
