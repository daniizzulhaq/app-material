<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Cashflow;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\StokMutasi;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogikaBisnisTest extends TestCase
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
    }

    private function beli(array $items, string $metode = 'tunai', string $tanggal = '2026-11-01')
    {
        return $this->post(route('pembelian-stok.store'), [
            'tanggal' => $tanggal, 'supplier_id' => $this->supplier->id, 'metode_bayar' => $metode, 'items' => $items,
        ]);
    }

    private function nota(array $items, string $tanggal = '2026-11-02')
    {
        return $this->post(route('transaksi.store'), ['tanggal' => $tanggal, 'klien_id' => $this->klien->id, 'items' => $items]);
    }

    public function test_pembelian_menambah_stok_dan_hpp_rata_rata_tertimbang(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]])->assertSessionHasNoErrors();
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 30, 'harga_beli' => 60000]])->assertSessionHasNoErrors();

        $this->semen->refresh();
        $this->assertEquals(40, (float) $this->semen->stok);
        // (10×50.000 + 30×60.000) / 40 = 57.500
        $this->assertEquals(57500, (float) $this->semen->hpp);
        $this->assertEquals(2, StokMutasi::where('tipe', 'masuk')->count());

        // Tunai → cashflow keluar pembelian_stok
        $this->assertEquals(2300000, (float) Cashflow::where('kategori', 'pembelian_stok')->sum('nominal'));
    }

    public function test_pembelian_hutang_tidak_membuat_cashflow_sampai_dibayar(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]], 'hutang');
        $pembelian = PembelianStok::first();

        $this->assertEquals('belum', $pembelian->status_bayar);
        $this->assertEquals(0, Cashflow::count());

        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'supplier', 'pembelian_stok_id' => $pembelian->id, 'jumlah' => 200000, 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('sebagian', $pembelian->fresh()->status_bayar);
        $this->assertDatabaseHas('cashflows', ['kategori' => 'pembayaran_supplier', 'tipe' => 'keluar', 'nominal' => 200000]);

        // Bayar melebihi sisa ditolak
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'supplier', 'pembelian_stok_id' => $pembelian->id, 'jumlah' => 400000, 'metode' => 'tunai',
        ])->assertSessionHasErrors('jumlah');

        // Hapus pembayaran → cashflow ikut hilang & status kembali
        Pembayaran::first()->delete();
        $this->assertEquals('belum', $pembelian->fresh()->status_bayar);
        $this->assertEquals(0, Cashflow::count());
    }

    public function test_nota_campuran_stok_dan_supplier(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]]);
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 300000, 'stok_minimum' => 0]);

        $this->nota([
            // harga_beli dari client untuk item stok diabaikan → server pakai HPP
            ['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 4, 'harga_beli' => 1, 'harga_jual' => 70000],
            ['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => 2, 'harga_beli' => 250000, 'harga_jual' => 300000],
        ])->assertSessionHasNoErrors();

        $t = Transaksi::first();
        $this->assertEquals(880000, (float) $t->total_jual);      // 280.000 + 600.000
        $this->assertEquals(700000, (float) $t->total_modal);     // 200.000 + 500.000
        $this->assertEquals(500000, (float) $t->total_belanja_supplier);
        $this->assertEquals(180000, (float) $t->laba);
        $this->assertEquals(6, (float) $this->semen->fresh()->stok);
        $this->assertEquals(0, (float) $pasir->fresh()->stok);   // sumber supplier tidak mengubah stok

        // Belanja supplier = tagihan (belum ada uang keluar sampai dibayar lewat Pembayaran)
        $this->assertEquals(0, Cashflow::where('kategori', 'belanja_supplier')->count());
        $this->assertEquals(0, Cashflow::where('kategori', 'pembayaran_supplier')->count());
    }

    public function test_bayar_supplier_per_tanggal_nota_dan_dijaga_saat_edit_hapus(): void
    {
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 300000, 'stok_minimum' => 0]);
        $item = fn ($beli) => [['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => 1, 'harga_beli' => $beli, 'harga_jual' => 300000]];

        $this->nota($item(100000));
        $this->nota($item(150000));

        // Nominal nota per hari untuk supplier ini = 250.000 (2 nota)
        $this->getJson(route('pembayaran.tagihan-supplier', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertJsonFragment(['value' => 'tanggal:2026-11-02', 'total' => 250000, 'sisa' => 250000]);

        // Jumlah boleh diketik dengan titik ribuan
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'supplier', 'supplier_id' => $this->supplier->id,
            'tagihan' => 'tanggal:2026-11-02', 'jumlah' => '200.000', 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $bayar = Pembayaran::first();
        $this->assertEquals('2026-11-02', $bayar->tanggal_nota->toDateString());
        $this->assertNull($bayar->pembelian_stok_id);
        $this->assertDatabaseHas('cashflows', ['kategori' => 'pembayaran_supplier', 'tipe' => 'keluar', 'nominal' => 200000]);

        // Melebihi sisa (50.000) ditolak
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'supplier', 'supplier_id' => $this->supplier->id,
            'tagihan' => 'tanggal:2026-11-02', 'jumlah' => '60.000', 'metode' => 'tunai',
        ])->assertSessionHasErrors('jumlah');

        // Laporan supplier memperhitungkan pembayaran per tanggal nota
        $this->get(route('laporan.supplier', ['dari' => '2026-11-01', 'sampai' => '2026-11-30']))
            ->assertOk()
            ->assertViewHas('total', fn ($t) => $t['belanja_langsung'] == 250000 && $t['dibayar'] == 200000 && $t['sisa'] == 50000);

        // Pindah nota 150.000 ke tanggal lain → belanja 02/11 jadi 100.000 < dibayar 200.000 → ditolak
        $nota2 = Transaksi::latest('id')->first();
        $this->put(route('transaksi.update', $nota2), ['tanggal' => '2026-11-04', 'klien_id' => $this->klien->id, 'items' => $item(150000)])
            ->assertSessionHas('error');
        $this->assertEquals('2026-11-02', $nota2->fresh()->tanggal->toDateString());

        // Hapus nota juga ditolak selama pembayarannya masih ada
        $this->delete(route('transaksi.destroy', $nota2))->assertSessionHas('error');
        $this->assertNotNull($nota2->fresh());
    }

    public function test_barang_baru_tidak_otomatis_masuk_data_stok(): void
    {
        $this->post(route('barang.store'), [
            'nama' => 'Gula 1kg', 'supplier_id' => $this->supplier->id, 'satuan' => 'pcs',
            'harga_jual_default' => '18.000', 'harga_beli_default' => '15.500',
        ])->assertSessionHasNoErrors();

        $gula = Barang::where('nama', 'Gula 1kg')->first();
        $this->assertEquals(18000, (float) $gula->harga_jual_default);
        $this->assertEquals(15500, (float) $gula->harga_beli_default);
        $this->assertEquals(0, $gula->mutasis()->count());
        $this->get(route('stok.index'))->assertOk()
            ->assertViewHas('barangs', fn ($b) => ! $b->getCollection()->contains('id', $gula->id));

        // Setelah dibeli lewat pembelian stok → tampil di data stok
        $this->beli([['barang_id' => $gula->id, 'qty' => '10', 'harga_beli' => '15.500']])->assertSessionHasNoErrors();
        $this->get(route('stok.index'))
            ->assertViewHas('barangs', fn ($b) => $b->getCollection()->contains('id', $gula->id));
        $this->assertEquals(15500, (float) $gula->fresh()->hpp);
    }

    public function test_rekap_bisa_filter_lebih_dari_satu_klien_dan_supplier(): void
    {
        $klien2 = Klien::create(['nama' => 'Klien Dua']);
        $klien3 = Klien::create(['nama' => 'Klien Tiga']);
        $supplier2 = Supplier::create(['nama' => 'UD Dua']);
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 300000, 'stok_minimum' => 0]);
        $buat = fn ($klien, $supplier) => $this->post(route('transaksi.store'), ['tanggal' => '2026-11-02', 'klien_id' => $klien->id, 'items' => [
            ['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $supplier->id, 'qty' => '1', 'harga_beli' => '100.000', 'harga_jual' => '150.000'],
        ]]);
        $buat($this->klien, $this->supplier);
        $buat($klien2, $supplier2);
        $buat($klien3, $supplier2);

        $this->get(route('transaksi.index', ['klien_id' => [$this->klien->id, $klien2->id]]))
            ->assertViewHas('ringkasan', fn ($r) => (int) $r->jumlah === 2);
        $this->get(route('transaksi.index', ['supplier_id' => [$this->supplier->id, $supplier2->id]]))
            ->assertViewHas('ringkasan', fn ($r) => (int) $r->jumlah === 3);
        $this->get(route('transaksi.index', ['supplier_id' => [$supplier2->id]]))
            ->assertViewHas('ringkasan', fn ($r) => (int) $r->jumlah === 2);

        // Laporan klien: 2 klien → rekap 2 baris; laporan supplier: 1 supplier
        $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'klien_id' => [$klien2->id, $klien3->id]]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 2)->assertViewHas('rows', fn ($rows) => $rows->pluck('nama')->sort()->values()->all() === ['Klien Dua', 'Klien Tiga']);
        $this->get(route('laporan.supplier', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id]]))
            ->assertOk()->assertViewHas('total', fn ($t) => $t['belanja_langsung'] == 200000);
        $this->get(route('laporan.barang', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'klien_id' => [$this->klien->id, $klien3->id]]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->first()->qty_total == 2);

        // Laporan klien difilter supplier: hanya nota berisi barang supplier2 (klien2 & klien3)
        $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id]]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 2);

        // Laporan supplier difilter klien: belanja supplier2 hanya dari klien2 (100.000);
        // bayar 100.000 untuk tanggal itu (belanja semua klien 200.000) → porsi klien2 = 50.000
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'supplier', 'supplier_id' => $supplier2->id,
            'tagihan' => 'tanggal:2026-11-02', 'jumlah' => '100.000', 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();
        $this->get(route('laporan.supplier', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id], 'klien_id' => [$klien2->id]]))
            ->assertOk()->assertViewHas('total', fn ($t) => $t['belanja_langsung'] == 100000 && $t['dibayar'] == 50000 && $t['sisa'] == 50000);

        // Laporan stok bisa difilter supplier barang
        $this->get(route('laporan.stok', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id]]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->isEmpty());

        // PDF laporan dibuka di browser (inline), unduh hanya bila diminta
        $pdf = $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'export' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('inline', $pdf->headers->get('content-disposition'));
        $unduh = $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'export' => 'pdf', 'unduh' => 1]));
        $this->assertStringStartsWith('attachment', $unduh->headers->get('content-disposition'));
    }

    public function test_nota_ditolak_jika_qty_melebihi_stok(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 5, 'harga_beli' => 50000]]);

        $this->nota([['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 6, 'harga_jual' => 70000]])
            ->assertSessionHas('error');

        $this->assertEquals(0, Transaksi::count());
        $this->assertEquals(5, (float) $this->semen->fresh()->stok);
    }

    public function test_edit_dan_hapus_nota_mengembalikan_stok(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]]);
        $this->nota([['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 4, 'harga_jual' => 70000]]);
        $nota = Transaksi::first();

        // Edit qty 4 → 10 (boleh, karena 4 milik nota ini dikembalikan dulu)
        $this->put(route('transaksi.update', $nota), ['tanggal' => '2026-11-02', 'klien_id' => $this->klien->id,
            'items' => [['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 10, 'harga_jual' => 70000]]])->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) $this->semen->fresh()->stok);

        $this->delete(route('transaksi.destroy', $nota))->assertSessionHas('success');
        $this->assertEquals(10, (float) $this->semen->fresh()->stok);
        $this->assertEquals(0, StokMutasi::where('referensi_tipe', 'transaksi')->count());
    }

    public function test_hapus_pembelian_ditolak_jika_stok_jadi_minus(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]]);
        $this->nota([['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 8, 'harga_jual' => 70000]]);

        $this->delete(route('pembelian-stok.destroy', PembelianStok::first()))->assertSessionHas('error');

        $this->assertEquals(1, PembelianStok::count());
        $this->assertEquals(2, (float) $this->semen->fresh()->stok);
    }

    public function test_pembayaran_klien_update_status_dan_cashflow_masuk(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]]);
        $this->nota([['barang_id' => $this->semen->id, 'sumber' => 'stok', 'qty' => 2, 'harga_jual' => 70000]]);
        $nota = Transaksi::first();

        $this->post(route('pembayaran.store'), ['tanggal' => '2026-11-02', 'jenis' => 'klien', 'transaksi_id' => $nota->id, 'jumlah' => 140000, 'metode' => 'transfer'])
            ->assertSessionHasNoErrors();

        $this->assertEquals('lunas', $nota->fresh()->status_bayar);
        $this->assertDatabaseHas('cashflows', ['kategori' => 'pembayaran_klien', 'tipe' => 'masuk', 'nominal' => 140000]);
        $this->assertEquals($this->klien->id, Pembayaran::first()->klien_id);

        // Nota yang sudah dibayar tidak bisa dihapus
        $this->delete(route('transaksi.destroy', $nota))->assertSessionHas('error');
    }

    public function test_stok_opname_menyesuaikan_stok_tanpa_cashflow(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 10, 'harga_beli' => 50000]]);
        $cashflowSebelum = Cashflow::count();

        $this->post(route('stok-opname.store'), ['tanggal' => now()->toDateString(), 'barang_id' => $this->semen->id, 'stok_fisik' => 8])
            ->assertSessionHasNoErrors();

        $this->assertEquals(8, (float) $this->semen->fresh()->stok);
        $this->assertDatabaseHas('stok_opnames', ['selisih' => -2, 'nilai_selisih' => -100000]);
        $this->assertDatabaseHas('stok_mutasis', ['tipe' => 'penyesuaian', 'qty' => -2]);
        $this->assertEquals($cashflowSebelum, Cashflow::count());
    }

    public function test_master_yang_sudah_dipakai_tidak_bisa_dihapus(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 1, 'harga_beli' => 50000]]);

        $this->delete(route('supplier.destroy', $this->supplier))->assertSessionHas('error');
        $this->delete(route('barang.destroy', $this->semen))->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $this->supplier->id]);

        // Klien yang belum dipakai boleh dihapus
        $this->delete(route('klien.destroy', $this->klien))->assertSessionHas('success');
    }

    public function test_cashflow_otomatis_tidak_bisa_diedit_atau_dihapus(): void
    {
        $this->beli([['barang_id' => $this->semen->id, 'qty' => 1, 'harga_beli' => 50000]]);
        $otomatis = Cashflow::first();

        $this->get(route('cashflow.edit', $otomatis))->assertRedirect(route('cashflow.index'));
        $this->delete(route('cashflow.destroy', $otomatis))->assertSessionHas('error');
        $this->assertDatabaseHas('cashflows', ['id' => $otomatis->id]);
    }

    public function test_ganti_password_butuh_password_lama(): void
    {
        $this->put(route('user.password'), ['password_lama' => 'salah', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertSessionHasErrorsIn('password', 'password_lama');

        $this->put(route('user.password'), ['password_lama' => 'password', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertSessionHasNoErrors();
    }
}
