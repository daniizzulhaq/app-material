<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Cashflow;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\RiwayatHapus;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiwayatHapusTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;
    private Klien $klien;
    private Barang $semen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->supplier = Supplier::create(['nama' => 'UD Test']);
        $this->klien = Klien::create(['nama' => 'Klien Test']);
        $this->semen = Barang::create(['nama' => 'Semen', 'satuan' => 'sak', 'harga_jual_default' => 70000, 'stok_minimum' => 5]);

        $this->post(route('pembelian-stok.store'), [
            'tanggal' => '2026-11-01', 'supplier_id' => $this->supplier->id, 'metode_bayar' => 'tunai',
            'items' => [['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]],
        ])->assertSessionHasNoErrors();
    }

    private function pulihkanTerakhir()
    {
        return $this->post(route('riwayat.pulihkan', RiwayatHapus::latest('id')->first()));
    }

    public function test_hapus_dan_pulihkan_nota_mengembalikan_stok_nomor_dan_hpp(): void
    {
        $this->post(route('transaksi.store'), [
            'tanggal' => '2026-11-02', 'klien_id' => $this->klien->id,
            'items' => [['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 4, 'harga_jual' => 70000]],
        ])->assertSessionHasNoErrors();
        $nota = Transaksi::first();
        $label = $nota->label();

        $this->delete(route('transaksi.destroy', $nota))->assertSessionHas('success');
        $this->assertEquals(10, (float) $this->semen->fresh()->stok);
        $this->assertDatabaseHas('riwayat_hapus', ['jenis' => 'transaksi', 'label' => $label, 'dipulihkan_at' => null]);

        // Harga beli naik setelah nota dihapus, tapi nota yang dipulihkan tetap memakai HPP lamanya
        $this->post(route('pembelian-stok.store'), [
            'tanggal' => '2026-11-03', 'supplier_id' => $this->supplier->id, 'metode_bayar' => 'tunai',
            'items' => [['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 70000]],
        ]);

        $this->pulihkanTerakhir()->assertSessionHas('success');

        $pulih = Transaksi::with('details')->first();
        $this->assertEquals('2026-11-02', $pulih->tanggal->toDateString());
        $this->assertEquals(50000, (float) $pulih->details->first()->harga_beli);
        $this->assertEquals(16, (float) $this->semen->fresh()->stok);
        $this->assertNotNull(RiwayatHapus::where('jenis', 'transaksi')->first()->dipulihkan_at);

        // Tidak bisa dipulihkan dua kali
        $this->pulihkanTerakhir()->assertSessionHas('error');
        $this->assertEquals(1, Transaksi::count());
    }

    public function test_hapus_dan_pulihkan_pembelian_dan_pembayaran(): void
    {
        $this->post(route('pembelian-stok.store'), [
            'tanggal' => '2026-11-02', 'supplier_id' => $this->supplier->id, 'metode_bayar' => 'hutang',
            'items' => [['barang_id' => $this->semen->id, 'qty' => 5, 'harga_beli' => 60000]],
        ]);
        $hutang = PembelianStok::where('metode_bayar', 'hutang')->first();
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'supplier', 'pembelian_stok_id' => $hutang->id, 'jumlah' => 300000, 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->delete(route('pembayaran.destroy', Pembayaran::first()))->assertSessionHas('success');
        $this->assertEquals('belum', $hutang->fresh()->status_bayar);

        $this->pulihkanTerakhir()->assertSessionHas('success');
        $this->assertEquals('lunas', $hutang->fresh()->status_bayar);
        $this->assertEquals(1, Cashflow::where('kategori', 'pembayaran_supplier')->count());
    }

    public function test_pulihkan_nota_ditolak_jika_kliennya_sudah_dihapus(): void
    {
        $this->post(route('transaksi.store'), [
            'tanggal' => '2026-11-02', 'klien_id' => $this->klien->id,
            'items' => [['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 1, 'harga_jual' => 70000]],
        ]);
        $this->delete(route('transaksi.destroy', Transaksi::first()));
        $riwayatNota = RiwayatHapus::latest('id')->first();
        $this->delete(route('klien.destroy', $this->klien))->assertSessionHas('success');

        $this->post(route('riwayat.pulihkan', $riwayatNota))->assertSessionHas('error');
        $this->assertEquals(0, Transaksi::count());

        // Pulihkan klien dulu (ID aslinya kembali), lalu notanya bisa dipulihkan
        $this->pulihkanTerakhir()->assertSessionHas('success');
        $this->assertDatabaseHas('kliens', ['id' => $this->klien->id, 'nama' => 'Klien Test']);

        $this->post(route('riwayat.pulihkan', $riwayatNota))->assertSessionHas('success');
        $this->assertEquals(1, Transaksi::count());
    }

    public function test_hapus_permanen_dan_halaman_riwayat(): void
    {
        $this->delete(route('supplier.destroy', Supplier::create(['nama' => 'UD Sementara'])));
        $this->get(route('riwayat.index'))->assertOk()->assertSee('UD Sementara');

        $this->delete(route('riwayat.destroy', RiwayatHapus::first()))->assertSessionHas('success');
        $this->assertEquals(0, RiwayatHapus::count());
    }
}
