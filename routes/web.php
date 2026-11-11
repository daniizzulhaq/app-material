<?php

use App\Http\Controllers\BarangController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KlienController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PembelianStokController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\StokController;
use App\Http\Controllers\StokOpnameController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Master
    Route::resource('supplier', SupplierController::class)->except('show');
    Route::resource('barang', BarangController::class)->except('show');
    Route::resource('klien', KlienController::class)->except('show')->parameters(['klien' => 'klien']);

    // Stok
    Route::resource('pembelian-stok', PembelianStokController::class)->parameters(['pembelian-stok' => 'pembelianStok']);
    Route::get('stok', [StokController::class, 'index'])->name('stok.index');
    Route::get('stok/{barang}', [StokController::class, 'show'])->name('stok.show');
    Route::resource('stok-opname', StokOpnameController::class)->only(['index', 'create', 'store', 'destroy'])
        ->parameters(['stok-opname' => 'stokOpname']);

    // Transaksi (Rekapan / Nota Penjualan)
    Route::get('transaksi/{transaksi}/cetak', [TransaksiController::class, 'cetak'])->name('transaksi.cetak');
    Route::resource('transaksi', TransaksiController::class);

    // Laporan
    Route::prefix('laporan')->name('laporan.')->controller(LaporanController::class)->group(function () {
        Route::get('klien', 'klien')->name('klien');
        Route::get('supplier', 'supplier')->name('supplier');
        Route::get('barang', 'barang')->name('barang');
        Route::get('stok', 'stok')->name('stok');
    });

    // Keuangan
    Route::get('pembayaran/nota', [PembayaranController::class, 'notaKlien'])->name('pembayaran.nota');
    Route::resource('pembayaran', PembayaranController::class)->except('show');
    Route::resource('cashflow', CashflowController::class)->except('show');

    // Pengaturan akun
    // Riwayat hapus
    Route::get('riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');
    Route::post('riwayat/{riwayat}/pulihkan', [RiwayatController::class, 'pulihkan'])->name('riwayat.pulihkan');
    Route::delete('riwayat/kosongkan', [RiwayatController::class, 'kosongkan'])->name('riwayat.kosongkan');
    Route::delete('riwayat/{riwayat}', [RiwayatController::class, 'destroy'])->name('riwayat.destroy');

    Route::get('user', [UserController::class, 'edit'])->name('user.edit');
    Route::put('user', [UserController::class, 'update'])->name('user.update');
    Route::put('user/password', [UserController::class, 'password'])->name('user.password');
});

require __DIR__.'/auth.php';
