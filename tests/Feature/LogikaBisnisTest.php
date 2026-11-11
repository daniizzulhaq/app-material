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
use Illuminate\Support\Facades\Schema;
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

        // Belanja supplier lunas otomatis: cashflow keluar harian tanggal nota
        $this->assertDatabaseHas('cashflows', ['kategori' => 'belanja_supplier', 'tipe' => 'keluar', 'nominal' => 500000, 'referensi_id' => 20261102]);
        $this->assertEquals(0, Cashflow::where('kategori', 'pembayaran_supplier')->count());
    }

    public function test_belanja_supplier_lunas_otomatis_per_hari(): void
    {
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 300000, 'stok_minimum' => 0]);
        $item = fn ($beli) => [['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => 1, 'harga_beli' => $beli, 'harga_jual' => 300000]];

        $this->nota($item(100000));
        $this->nota($item(150000));

        // Satu baris cashflow per hari = total nota beli supplier hari itu
        $harian = fn ($tgl) => (float) Cashflow::where('kategori', 'belanja_supplier')->whereDate('tanggal', $tgl)->sum('nominal');
        $this->assertEquals(1, Cashflow::where('kategori', 'belanja_supplier')->count());
        $this->assertEquals(250000, $harian('2026-11-02'));

        // Pindah tanggal nota → dua hari dihitung ulang
        $nota2 = Transaksi::latest('id')->first();
        $this->put(route('transaksi.update', $nota2), ['tanggal' => '2026-11-04', 'klien_id' => $this->klien->id, 'items' => $item(150000)])
            ->assertSessionHasNoErrors();
        $this->assertEquals(100000, $harian('2026-11-02'));
        $this->assertEquals(150000, $harian('2026-11-04'));

        // Hapus nota → baris hari itu hilang
        $this->delete(route('transaksi.destroy', $nota2))->assertSessionHas('success');
        $this->assertEquals(0, Cashflow::where('kategori', 'belanja_supplier')->whereDate('tanggal', '2026-11-04')->count());

        // Tidak ada lagi pembayaran ke supplier untuk nota (hanya hutang pembelian stok)
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'supplier', 'supplier_id' => $this->supplier->id, 'jumlah' => '100.000', 'metode' => 'tunai',
        ])->assertSessionHasErrors('pembelian_stok_id');
        $this->assertEquals(0, Pembayaran::count());
    }

    public function test_pembayaran_klien_dikaitkan_ke_nota_per_nota(): void
    {
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 300000, 'stok_minimum' => 0]);
        $item = [['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => 2, 'harga_beli' => 200000, 'harga_jual' => 250000]];
        $this->nota($item, '2026-11-02');
        $this->nota($item, '2026-11-03');
        [$nota1, $nota2] = Transaksi::orderBy('id')->get();
        $lain = Klien::create(['nama' => 'Klien Lain']);

        // Daftar nota klien yang belum lunas (untuk "Kaitkan ke Nota")
        $this->getJson(route('pembayaran.nota', ['klien_id' => $this->klien->id]))
            ->assertOk()->assertJsonCount(2)->assertJsonFragment(['value' => (string) $nota1->id, 'sisa' => 500000]);
        $this->getJson(route('pembayaran.nota', ['klien_id' => $lain->id]))->assertOk()->assertJsonCount(0);

        // Nota milik klien lain ditolak
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-04', 'jenis' => 'klien', 'klien_id' => $lain->id, 'transaksi_id' => $nota1->id, 'jumlah' => '100.000', 'metode' => 'tunai',
        ])->assertSessionHasErrors('transaksi_id');

        // Melebihi sisa nota ditolak
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-04', 'jenis' => 'klien', 'klien_id' => $this->klien->id, 'transaksi_id' => $nota1->id, 'jumlah' => '600.000', 'metode' => 'tunai',
        ])->assertSessionHasErrors('jumlah');

        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-04', 'jenis' => 'klien', 'klien_id' => $this->klien->id, 'transaksi_id' => $nota1->id, 'jumlah' => '500.000', 'metode' => 'transfer',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('lunas', $nota1->fresh()->status_bayar);
        $this->assertEquals('belum', $nota2->fresh()->status_bayar);

        // Pembayaran tanpa nota tetap boleh (uang masuk)
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-05', 'jenis' => 'klien', 'klien_id' => $this->klien->id, 'jumlah' => '50.000', 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(550000, (float) Cashflow::where('kategori', 'pembayaran_klien')->sum('nominal'));

        // Nota lunas tidak muncul lagi di pilihan
        $this->getJson(route('pembayaran.nota', ['klien_id' => $this->klien->id]))->assertJsonCount(1);
        $this->get(route('pembayaran.index'))->assertOk()->assertViewHas('ringkasan', fn ($r) => (float) $r->masuk === 550000.0);
    }

    public function test_modal_berkurang_nota_beli_dan_kembali_dari_pembayaran_klien(): void
    {
        $this->post(route('cashflow.store'), [
            'tanggal' => '2026-11-01', 'tipe' => 'masuk', 'kategori' => 'modal', 'nominal' => '1.000.000', 'keterangan' => 'Modal awal',
        ])->assertSessionHasNoErrors();

        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 0, 'stok_minimum' => 0]);
        // Nota beli 400.000, jual 500.000
        $this->nota([['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => 1, 'harga_beli' => 400000, 'harga_jual' => 500000]]);
        $nota = Transaksi::first();

        // Klien bayar separuh → modal kembali 200.000 (bagian harga beli)
        $this->post(route('pembayaran.store'), [
            'tanggal' => '2026-11-03', 'jenis' => 'klien', 'klien_id' => $this->klien->id, 'transaksi_id' => $nota->id, 'jumlah' => 250000, 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->get(route('cashflow.index', ['dari' => '2026-11-01', 'sampai' => '2026-11-30']))
            ->assertOk()
            ->assertViewHas('modal', fn ($m) => $m['tambahan'] == 1000000 && $m['belanja'] == 400000
                && $m['kembali'] == 200000 && $m['saldo_akhir'] == 800000);

        // Periode berikutnya: modal awal = saldo akhir sebelumnya
        $this->get(route('cashflow.index', ['dari' => '2026-12-01', 'sampai' => '2026-12-31']))
            ->assertViewHas('modal', fn ($m) => $m['saldo_awal'] == 800000);

        $this->get(route('cashflow.create', ['kategori' => 'modal']))->assertOk()->assertSee('Modal awal');
    }

    public function test_nominal_nota_tidak_hilang_nol_saat_validasi_gagal(): void
    {
        $pasir = Barang::create(['nama' => 'Pasir', 'satuan' => 'm3', 'harga_jual_default' => 0, 'stok_minimum' => 0]);

        // Satu kolom lupa diisi (harga jual baris ke-2) → kembali ke form dengan input lama
        $this->from(route('transaksi.create'))->post(route('transaksi.store'), ['tanggal' => '2026-11-02', 'klien_id' => $this->klien->id, 'items' => [
            ['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => '1,5', 'harga_beli' => '64.000', 'harga_jual' => '1.250.000'],
            ['barang_id' => $pasir->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => '1000', 'harga_beli' => '50.000', 'harga_jual' => ''],
        ]])->assertSessionHasErrors('items.1.harga_jual');

        $this->get(route('transaksi.create'))->assertOk()->assertViewHas('items', function ($items) {
            return (float) $items[0]['harga_beli'] === 64000.0 && (float) $items[0]['harga_jual'] === 1250000.0
                && (float) $items[0]['qty'] === 1.5 && (float) $items[1]['qty'] === 1000.0 && (float) $items[1]['harga_beli'] === 50000.0;
        });
    }

    public function test_nota_tanpa_nomor(): void
    {
        $this->nota([['barang_id' => $this->semen->id, 'sumber' => 'supplier', 'supplier_id' => $this->supplier->id, 'qty' => 1, 'harga_beli' => 50000, 'harga_jual' => 70000]])
            ->assertSessionHasNoErrors();
        $nota = Transaksi::first();

        $this->assertFalse(Schema::hasColumn('transaksis', 'no_nota'));
        $this->assertSame('Nota Klien Test · ' . tanggal('2026-11-02'), $nota->label());
        $this->get(route('transaksi.show', $nota))->assertOk()->assertDontSee('No. Nota');
        $this->get(route('transaksi.index', ['q' => 'Klien Test']))->assertViewHas('ringkasan', fn ($r) => (int) $r->jumlah === 1);
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
            ->assertOk()->assertViewHas('total', fn ($t) => $t['total_beli'] == 200000)
            // Daftar barang per supplier (seperti web sayur), klien disembunyikan → qty semua klien digabung
            ->assertViewHas('rincian', fn ($r) => $r->count() === 1 && $r->first()->items->count() === 1 && $r->first()->items->first()->qty == 2)
            ->assertSee('Barang dari UD Dua');
        $this->get(route('laporan.supplier', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id], 'klien' => 'tampil']))
            ->assertOk()->assertViewHas('rincian', fn ($r) => $r->first()->items->count() === 2);
        $this->get(route('laporan.supplier', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id], 'qty' => 'rincian']))
            ->assertOk()->assertSee('1 + 1 m3');
        $this->get(route('laporan.barang', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'klien_id' => [$this->klien->id, $klien3->id]]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()->qty == 2);
        $this->get(route('laporan.barang', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'klien' => 'tampil']))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 3);

        // Laporan klien: tampilan awal Klien · Kategori · Total Jual · Total Beli · Laba (tanpa dibayar/sisa)
        $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30']))
            ->assertOk()->assertSee('Total Beli')->assertDontSee('Sisa Piutang')->assertDontSee('Dibayar')
            ->assertViewHas('rincian', fn ($r) => $r->isEmpty())
            ->assertViewHas('total', fn ($t) => $t['total_jual'] == 450000 && $t['total_beli'] == 300000 && $t['laba'] == 150000);
        // Klien dipilih → rincian barang; mode harga "Jual saja" menyembunyikan harga beli & laba
        $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'klien_id' => [$klien2->id, $klien3->id], 'harga' => 'jual']))
            ->assertOk()->assertViewHas('rincian', fn ($r) => $r->count() === 2)->assertDontSee('Total Beli')->assertDontSee('Harga Beli');
        $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'klien_id' => [$klien2->id, $klien3->id], 'klien' => 'sembunyi']))
            ->assertOk()->assertViewHas('rincian', fn ($r) => $r->count() === 1 && $r->first()->items->count() === 1);

        // Laporan klien difilter supplier: hanya nota berisi barang supplier2 (klien2 & klien3)
        $this->get(route('laporan.klien', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id]]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 2);

        // Laporan supplier difilter klien: belanja supplier2 hanya dari klien2 (100.000)
        $this->get(route('laporan.supplier', ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'supplier_id' => [$supplier2->id], 'klien_id' => [$klien2->id]]))
            ->assertOk()->assertViewHas('total', fn ($t) => $t['total_beli'] == 100000);

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

        $this->post(route('pembayaran.store'), ['tanggal' => '2026-11-02', 'jenis' => 'klien', 'klien_id' => $this->klien->id, 'transaksi_id' => $nota->id, 'jumlah' => 140000, 'metode' => 'transfer'])
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
