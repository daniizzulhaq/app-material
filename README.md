# Rekap Bisnis Material

Aplikasi admin (1 user) untuk toko bahan bangunan: nota penjualan dari supplier langsung maupun stok gudang, pembelian stok dengan HPP rata-rata tertimbang, kartu stok, stok opname, pembayaran, cashflow, dan laporan (PDF/Excel).

**Stack:** Laravel 12 · PHP 8.2+ · MySQL/MariaDB · Blade + Bootstrap 5 · Chart.js · Tom Select · SweetAlert2 · DomPDF · Laravel Excel

## Menjalankan

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate   # lalu atur DB_* di .env
php artisan migrate:fresh --seed
php artisan serve
```

Login: **admin@admin.com / password**

Seeder `DemoTransaksiSeeder` mengisi contoh transaksi bulan berjalan. Hapus barisnya di `DatabaseSeeder` jika ingin data kosong.

## Mengunggah ke GitHub

Folder `public/build` disertakan dalam repository agar manifest, CSS, JavaScript, dan font produksi tersedia setelah clone. Setelah mengubah frontend, jalankan `npm run build`, lalu sertakan seluruh perubahan `public/build` dalam commit yang sama dengan perubahan source.

File `.env`, folder `node_modules` dan `vendor`, serta `public/hot` tetap diabaikan. Gunakan `.env.example` sebagai contoh konfigurasi dan jalankan `composer install` di server. File `public/hot` hanya untuk Vite saat pengembangan lokal.

## Tes

Tes memakai database MySQL `rekap_material_test` (lihat `phpunit.xml`); buat database ini terlebih dahulu.

```bash
php artisan test
```

## Struktur logika bisnis

| File | Tanggung jawab |
|---|---|
| `app/Services/StokService.php` | Mutasi stok, HPP rata-rata tertimbang, saldo berjalan kartu stok, stok opname |
| `app/Services/CashflowService.php` | Baris cashflow otomatis (pembelian tunai, belanja supplier harian, pembayaran) |
| `app/Services/PembelianStokService.php` | Simpan/edit/hapus pembelian stok |
| `app/Services/TransaksiService.php` | Simpan/edit/hapus nota penjualan |
| `app/Observers/PembayaranObserver.php` | Sinkron cashflow & status bayar saat pembayaran dibuat/diubah/dihapus |
| `app/Services/LaporanService.php` | Query semua laporan & top 3 dashboard |

Prinsip: **cashflow** = kapan uang keluar/masuk; **laba** = jual − modal barang terjual. Keduanya dihitung terpisah.

- Setiap perubahan stok ditulis sebagai baris `stok_mutasis`, lalu `StokService::hitungUlang()` memutar ulang mutasi barang (urut tanggal) untuk mendapatkan stok, HPP, dan saldo berjalan. Edit/hapus dokumen lama (termasuk yang bertanggal mundur) jadi selalu konsisten. Proses ditolak bila stok akhir menjadi minus.
- Item nota dari stok memakai HPP saat nota diinput (terkunci; saat nota diedit, HPP lama dipertahankan).
- Belanja supplier dari nota menjadi satu baris cashflow per tanggal (`referensi_id` = `Ymd`).
- Semua proses yang mengubah stok/cashflow berjalan di `DB::transaction()` dengan `lockForUpdate()` pada barang.
