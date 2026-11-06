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

        // Cashflow belanja supplier harian
        $this->assertDatabaseHas('cashflows', ['kategori' => 'belanja_supplier', 'referensi_id' => 20261102, 'nominal' => 500000]);
    }

    public function test_belanja_supplier_satu_baris_per_hari_dan_ikut_edit_hapus(): void
    {
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 300000, 'stok_minimum' => 0]);
        $item = fn ($beli) => [['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => 1, 'harga_beli' => $beli, 'harga_jual' => 300000]];

        $this->nota($item(100000));
        $this->nota($item(150000));
        $this->assertEquals(1, Cashflow::where('kategori', 'belanja_supplier')->count());
        $this->assertEquals(250000, (float) Cashflow::where('kategori', 'belanja_supplier')->value('nominal'));

        // Edit nota pertama ke tanggal lain → kedua tanggal dihitung ulang
        $nota1 = Transaksi::first();
        $this->put(route('transaksi.update', $nota1), ['tanggal' => '2026-11-04', 'klien_id' => $this->klien->id, 'items' => $item(100000)])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cashflows', ['referensi_id' => 20261102, 'nominal' => 150000]);
        $this->assertDatabaseHas('cashflows', ['referensi_id' => 20261104, 'nominal' => 100000]);

        $this->delete(route('transaksi.destroy', $nota1));
        $this->assertDatabaseMissing('cashflows', ['referensi_id' => 20261104]);
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
