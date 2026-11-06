<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Cashflow;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Smoke test: semua halaman terbuka tanpa error (database kosong & berisi data) */
class HalamanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class); // hanya user admin
        $this->actingAs(User::where('email', 'admin@admin.com')->first());
    }

    /** Data contoh minimal, dibuat lewat alur aplikasi yang sebenarnya */
    private function isiData(): void
    {
        $supplier = Supplier::create(['nama' => 'UD Test']);
        $klien = Klien::create(['nama' => 'Klien Test']);
        $semen = Barang::create(['nama' => 'Semen', 'satuan' => 'sak', 'harga_jual_default' => 70000, 'stok_minimum' => 50]);
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 300000, 'stok_minimum' => 0]);

        $this->post(route('pembelian-stok.store'), [
            'tanggal' => '2026-11-01', 'supplier_id' => $supplier->id, 'metode_bayar' => 'hutang',
            'items' => [['barang_id' => $semen->id, 'qty' => 20, 'harga_beli' => 60000]],
        ])->assertSessionHasNoErrors();
        $this->post(route('transaksi.store'), [
            'tanggal' => '2026-11-02', 'klien_id' => $klien->id, 'items' => [
                ['barang_id' => $semen->id, 'sumber' => 'stok', 'qty' => 5, 'harga_jual' => 70000],
                ['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $supplier->id, 'qty' => 2, 'harga_beli' => 250000, 'harga_jual' => 300000],
            ],
        ])->assertSessionHasNoErrors();
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'klien', 'transaksi_id' => Transaksi::first()->id, 'jumlah' => 100000, 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();
        $this->post(route('cashflow.store'), [
            'tanggal' => '2026-11-03', 'tipe' => 'keluar', 'kategori' => 'operasional', 'nominal' => 50000, 'keterangan' => 'Listrik',
        ])->assertSessionHasNoErrors();
        $this->post(route('stok-opname.store'), ['tanggal' => '2026-11-04', 'barang_id' => $semen->id, 'stok_fisik' => 14])->assertSessionHasNoErrors();
        $this->delete(route('cashflow.destroy', Cashflow::where('kategori', 'operasional')->first()));
    }

    public function test_halaman_daftar_bisa_dibuka_saat_database_kosong(): void
    {
        $urls = [
            'dashboard', 'supplier.index', 'supplier.create', 'barang.index', 'barang.create', 'klien.index', 'klien.create',
            'pembelian-stok.index', 'pembelian-stok.create', 'stok.index', 'stok-opname.index', 'stok-opname.create',
            'transaksi.index', 'transaksi.create', 'pembayaran.index', 'pembayaran.create', 'cashflow.index', 'cashflow.create',
            'laporan.klien', 'laporan.supplier', 'laporan.barang', 'laporan.stok', 'riwayat.index', 'user.edit',
        ];

        foreach ($urls as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_semua_halaman_bisa_dibuka(): void
    {
        $this->isiData();
        $this->post(route('cashflow.store'), [
            'tanggal' => '2026-11-05', 'tipe' => 'masuk', 'kategori' => 'modal', 'nominal' => 1000000, 'keterangan' => 'Modal awal',
        ]);

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
            route('riwayat.index'), route('riwayat.index', ['status' => 'semua']),
            route('user.edit'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_export_pdf_dan_excel(): void
    {
        $this->isiData();

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
