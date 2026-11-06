<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Cashflow;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Transaksi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Smoke test: semua halaman terbuka tanpa error dengan data demo */
class HalamanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@admin.com')->first());
    }

    public function test_semua_halaman_bisa_dibuka(): void
    {
        $transaksi = Transaksi::first();
        $pembelian = PembelianStok::first();
        $barang = Barang::first();
        $manual = Cashflow::whereNull('referensi_tipe')->first();

        $urls = [
            route('dashboard'),
            route('dashboard', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']),
            route('supplier.index'), route('supplier.create'), route('supplier.edit', 1),
            route('barang.index'), route('barang.create'), route('barang.edit', $barang),
            route('klien.index'), route('klien.create'), route('klien.edit', 1),
            route('pembelian-stok.index'), route('pembelian-stok.create'),
            route('pembelian-stok.show', $pembelian), route('pembelian-stok.edit', $pembelian),
            route('stok.index'), route('stok.index', ['menipis' => 1]), route('stok.show', $barang),
            route('stok-opname.index'), route('stok-opname.create'),
            route('transaksi.index'), route('transaksi.index', ['sumber' => 'stok', 'supplier_id' => 1]),
            route('transaksi.create'), route('transaksi.show', $transaksi), route('transaksi.edit', $transaksi),
            route('transaksi.cetak', $transaksi),
            route('pembayaran.index'), route('pembayaran.create'), route('pembayaran.create', ['jenis' => 'supplier']),
            route('pembayaran.edit', Pembayaran::first()),
            route('cashflow.index'), route('cashflow.create'), route('cashflow.edit', $manual),
            route('laporan.klien'), route('laporan.klien', ['klien_id' => $transaksi->klien_id]),
            route('laporan.supplier'), route('laporan.barang'), route('laporan.stok'),
            route('user.edit'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_export_pdf_dan_excel(): void
    {
        foreach (['klien', 'supplier', 'barang', 'stok'] as $jenis) {
            $this->get(route("laporan.{$jenis}", ['export' => 'pdf']))
                ->assertOk()->assertHeader('content-type', 'application/pdf');

            $this->get(route("laporan.{$jenis}", ['export' => 'excel']))
                ->assertOk()->assertDownload();
        }

        $this->get(route('transaksi.cetak', [Transaksi::first(), 'pdf' => 1]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
