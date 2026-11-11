<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Klien;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriKlienTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_klien_wajib_punya_kategori_yang_valid(): void
    {
        $this->post(route('klien.store'), ['nama' => 'Tanpa Kategori'])->assertSessionHasErrors('kategori');
        $this->post(route('klien.store'), ['nama' => 'Salah', 'kategori' => 'elektronik'])->assertSessionHasErrors('kategori');

        $this->post(route('klien.store'), ['nama' => 'Warung Bu Ani', 'kategori' => 'groceries'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kliens', ['nama' => 'Warung Bu Ani', 'kategori' => 'groceries']);
    }

    public function test_barang_bisa_punya_supplier_langganan(): void
    {
        $supplier = Supplier::create(['nama' => 'CV Pasir Jaya']);

        $this->post(route('barang.store'), [
            'nama' => 'Pasir Beton', 'supplier_id' => $supplier->id, 'satuan' => 'm3', 'harga_jual_default' => 320000, 'stok_minimum' => 0,
        ])->assertSessionHasNoErrors();
        $this->post(route('barang.store'), [
            'nama' => 'Tanpa Supplier', 'satuan' => 'pcs', 'harga_jual_default' => 1000, 'stok_minimum' => 0,
        ])->assertSessionHasNoErrors();
        $this->post(route('barang.store'), [
            'nama' => 'Salah', 'supplier_id' => 999, 'satuan' => 'pcs', 'harga_jual_default' => 1000, 'stok_minimum' => 0,
        ])->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseHas('barangs', ['nama' => 'Pasir Beton', 'supplier_id' => $supplier->id]);
        $this->get(route('barang.index', ['supplier_id' => $supplier->id]))
            ->assertOk()->assertSee('Pasir Beton')->assertDontSee('Tanpa Supplier');
        $this->get(route('barang.edit', Barang::where('nama', 'Pasir Beton')->first()))->assertOk()->assertSee('CV Pasir Jaya');

        // Data supplier langganan dikirim ke form nota untuk auto-isi
        $this->get(route('transaksi.create'))->assertOk()->assertSee('"supplier_id":' . $supplier->id, false);

        // Supplier dihapus → barang tetap ada, supplier-nya kosong
        $this->delete(route('supplier.destroy', $supplier))->assertSessionHas('success');
        $this->assertDatabaseHas('barangs', ['nama' => 'Pasir Beton', 'supplier_id' => null]);
    }

    public function test_laporan_klien_bisa_difilter_per_kategori(): void
    {
        $supplier = Supplier::create(['nama' => 'UD Test']);
        $barang = Barang::create(['nama' => 'Gula 1kg', 'satuan' => 'pcs', 'harga_jual_default' => 18000, 'stok_minimum' => 0]);
        $grocer = Klien::create(['nama' => 'Warung Bu Ani', 'kategori' => 'groceries']);
        $kontraktor = Klien::create(['nama' => 'CV Bangun', 'kategori' => 'material']);

        foreach ([[$grocer, 100000], [$kontraktor, 300000]] as [$klien, $harga]) {
            $this->post(route('transaksi.store'), [
                'tanggal' => now()->toDateString(), 'klien_id' => $klien->id,
                'items' => [['barang_id' => $barang->id, 'sumber' => 'supplier', 'supplier_id' => $supplier->id, 'qty' => 1, 'harga_beli' => 50000, 'harga_jual' => $harga]],
            ])->assertSessionHasNoErrors();
        }
        // Habiskan flash "Nota CV Bangun ... berhasil disimpan" agar tidak ikut terbaca di halaman laporan
        $this->get(route('dashboard'));

        $this->get(route('laporan.klien', ['kategori' => 'groceries']))
            ->assertOk()->assertSee('Warung Bu Ani')->assertDontSee('CV Bangun')
            ->assertSee('Laporan Per Klien Groceries');

        $this->get(route('laporan.klien', ['kategori' => 'material']))
            ->assertOk()->assertSee('CV Bangun')->assertDontSee('Warung Bu Ani');

        // Semua klien: subtotal per kategori + total keseluruhan
        $this->get(route('laporan.klien'))
            ->assertOk()->assertSee('Total Klien Groceries')->assertSee('Total Klien Material')
            ->assertSee('Rp 400.000');

        $this->get(route('laporan.klien', ['kategori' => 'groceries', 'export' => 'pdf']))->assertOk();
        $this->get(route('laporan.klien', ['kategori' => 'material', 'export' => 'excel']))->assertOk()->assertDownload();

        $this->get(route('transaksi.index', ['kategori' => 'groceries']))->assertOk()->assertSee('Warung Bu Ani')->assertDontSee('CV Bangun');
        $this->get(route('klien.index', ['kategori' => 'material']))->assertOk()->assertSee('CV Bangun')->assertDontSee('Warung Bu Ani');
    }
}
