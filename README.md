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
| `app/Services/ModalService.php` | Perputaran modal: modal − total nota beli per hari + bagian beli yang dibayar klien |
| `app/Services/PembelianStokService.php` | Simpan/edit/hapus pembelian stok |
| `app/Services/TransaksiService.php` | Simpan/edit/hapus nota penjualan |
| `app/Observers/PembayaranObserver.php` | Sinkron cashflow & status bayar saat pembayaran dibuat/diubah/dihapus |
| `app/Services/LaporanService.php` | Query semua laporan & top 3 dashboard |
| `app/Support/OpsiLaporan.php` | Opsi laporan: format qty (Total / 1 + 1), mode harga, tampilkan/sembunyikan klien |

Prinsip: **cashflow** = kapan uang keluar/masuk; **laba** = jual − modal barang terjual. Keduanya dihitung terpisah.

- Setiap perubahan stok ditulis sebagai baris `stok_mutasis`, lalu `StokService::hitungUlang()` memutar ulang mutasi barang (urut tanggal) untuk mendapatkan stok, HPP, dan saldo berjalan. Edit/hapus dokumen lama (termasuk yang bertanggal mundur) jadi selalu konsisten. Proses ditolak bila stok akhir menjadi minus.
- Item nota dari stok memakai HPP saat nota diinput (terkunci; saat nota diedit, HPP lama dipertahankan).
- Nota tanpa nomor: dikenali dari klien + tanggal (`Transaksi::label()`).
- Belanja supplier dari nota (item sumber supplier) **lunas otomatis per hari**: satu baris cashflow keluar "Belanja supplier harian" per tanggal nota, dihitung ulang saat nota dibuat/diedit/dihapus.
- Menu Pembayaran hanya uang masuk dari klien, dan bisa dikaitkan ke nota (per nota) untuk memperbarui status bayar. Hutang pembelian stok dibayar dari halaman detail Pembelian Stok.
- Cashflow punya ringkasan **Perputaran Modal**: modal awal (cashflow manual kategori Modal) otomatis dikurangi total nota beli per hari dan ditambah bagian harga beli dari tiap pembayaran klien yang dikaitkan ke nota (proporsional; laba tidak ikut).
- Laporan: opsi format qty, mode harga (lengkap / jual / beli / tanpa harga), dan tampilkan/sembunyikan klien. Laporan klien tampil awal Klien · Kategori · Total Jual · Total Beli · Laba; laporan supplier menampilkan daftar barang per supplier.
- Pada instalasi yang sudah ada jalankan `php artisan migrate`: kolom nomor nota dihapus, pembayaran supplier per tanggal nota (revisi 2) diganti baris belanja harian otomatis.
- Barang master tidak otomatis masuk data stok; barang tampil di Stok setelah dibeli lewat Pembelian Stok.
- Semua proses yang mengubah stok/cashflow berjalan di `DB::transaction()` dengan `lockForUpdate()` pada barang.
