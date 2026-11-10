<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Query agregat untuk semua laporan (dipakai halaman, PDF, dan Excel) */
class LaporanService
{
    /** Subquery: total pembayaran per nota */
    private function bayarPerNota()
    {
        return Pembayaran::query()
            ->whereNotNull('transaksi_id')
            ->selectRaw('transaksi_id, SUM(jumlah) AS dibayar')
            ->groupBy('transaksi_id');
    }

    /**
     * Per klien. Klien bisa dipilih lebih dari satu.
     * Tepat 1 klien dipilih → daftar nota klien tsb; selain itu → rekap per klien (yang dipilih / semua).
     * Filter supplier (bisa lebih dari satu) = hanya nota yang berisi barang dari supplier tsb.
     *
     * @param  int[]  $klienIds
     * @param  int[]  $supplierIds
     */
    public function klien(string $dari, string $sampai, array $klienIds = [], ?string $kategori = null, array $supplierIds = []): array
    {
        $kategori = array_key_exists((string) $kategori, Klien::KATEGORI) ? $kategori : null;
        $klienId = count($klienIds) === 1 ? $klienIds[0] : null;

        $base = Transaksi::query()
            ->leftJoinSub($this->bayarPerNota(), 'bayar', 'bayar.transaksi_id', '=', 'transaksis.id')
            ->whereBetween('transaksis.tanggal', [$dari, $sampai])
            ->when($klienIds, fn ($q) => $q->whereIn('transaksis.klien_id', $klienIds))
            ->when($supplierIds, fn ($q) => $q->whereHas('details', fn ($d) => $d->whereIn('supplier_id', $supplierIds)))
            // Kategori klien: groceries / material
            ->when($kategori, fn ($q) => $q->whereIn('transaksis.klien_id', Klien::kategori($kategori)->select('id')));

        if ($klienId) {
            $rows = $base->select('transaksis.*', DB::raw('COALESCE(bayar.dibayar, 0) AS dibayar'))
                ->orderBy('transaksis.tanggal')->orderBy('transaksis.id')
                ->get()
                ->map(fn ($t) => (object) [
                    'no_nota' => $t->no_nota,
                    'tanggal' => $t->tanggal,
                    'status_bayar' => $t->status_bayar,
                    'jumlah_nota' => 1,
                    'total_jual' => (float) $t->total_jual,
                    'dibayar' => (float) $t->dibayar,
                    'sisa' => max(0, (float) $t->total_jual - (float) $t->dibayar),
                    'laba' => (float) $t->laba,
                ]);
        } else {
            $agregat = $base->selectRaw('
                    transaksis.klien_id,
                    COUNT(*) AS jumlah_nota,
                    SUM(transaksis.total_jual) AS total_jual,
                    SUM(COALESCE(bayar.dibayar, 0)) AS dibayar,
                    SUM(transaksis.laba) AS laba
                ')
                ->groupBy('transaksis.klien_id')
                ->get();

            $kliens = Klien::whereIn('id', $agregat->pluck('klien_id'))->get(['id', 'nama', 'kategori'])->keyBy('id');

            $rows = $agregat->map(fn ($r) => (object) [
                'nama' => $kliens[$r->klien_id]->nama ?? '-',
                'kategori' => $kliens[$r->klien_id]->kategori ?? null,
                'jumlah_nota' => (int) $r->jumlah_nota,
                'total_jual' => (float) $r->total_jual,
                'dibayar' => (float) $r->dibayar,
                'sisa' => max(0, (float) $r->total_jual - (float) $r->dibayar),
                'laba' => (float) $r->laba,
            ])->sortByDesc('total_jual')->values();
        }

        return [
            'rows' => $rows,
            'klien' => $klienId ? Klien::find($klienId) : null,
            'kategori' => $kategori,
            'total' => $this->jumlahkan($rows, ['jumlah_nota', 'total_jual', 'dibayar', 'sisa', 'laba']),
            // Subtotal per kategori (hanya saat rekap semua klien tanpa filter kategori)
            'totalKategori' => ($klienIds || $kategori) ? [] : collect(Klien::KATEGORI)->map(fn ($k, $val) => $this->jumlahkan(
                $rows->where('kategori', $val), ['jumlah_nota', 'total_jual', 'dibayar', 'sisa', 'laba']
            ))->all(),
        ];
    }

    /**
     * Per supplier (bisa lebih dari satu): belanja langsung (item nota sumber supplier) + pembelian stok.
     * Belanja langsung dibayar lewat menu Pembayaran per supplier per tanggal nota.
     * Filter klien (bisa lebih dari satu): hanya belanja dari nota klien tsb; pembayaran per tanggal
     * (gabungan semua klien) dibagi proporsional sesuai porsi belanja klien terpilih.
     * Pembelian stok tidak terkait klien, jadi tidak dihitung saat filter klien aktif.
     *
     * @param  int[]  $supplierIds
     * @param  int[]  $klienIds
     */
    public function supplier(string $dari, string $sampai, array $supplierIds = [], array $klienIds = []): array
    {
        $belanjaHarian = fn (array $kliens) => TransaksiDetail::query()
            ->join('transaksis', 'transaksis.id', '=', 'transaksi_details.transaksi_id')
            ->where('transaksi_details.sumber', 'supplier')
            ->whereBetween('transaksis.tanggal', [$dari, $sampai])
            ->when($supplierIds, fn ($q) => $q->whereIn('transaksi_details.supplier_id', $supplierIds))
            ->when($kliens, fn ($q) => $q->whereIn('transaksis.klien_id', $kliens))
            ->selectRaw('transaksi_details.supplier_id, transaksis.tanggal, SUM(transaksi_details.subtotal_beli) AS total')
            ->groupBy('transaksi_details.supplier_id', 'transaksis.tanggal')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->supplier_id . '|' . substr((string) $r->tanggal, 0, 10) => (float) $r->total]);

        $belanjaHari = $belanjaHarian($klienIds);
        $semuaKlienHari = $klienIds ? $belanjaHarian([]) : $belanjaHari;

        // Pembayaran belanja nota per tanggal nota (gabungan semua klien)
        $bayarHari = Pembayaran::query()
            ->where('jenis', 'supplier')
            ->whereNotNull('tanggal_nota')
            ->whereBetween('tanggal_nota', [$dari, $sampai])
            ->when($supplierIds, fn ($q) => $q->whereIn('supplier_id', $supplierIds))
            ->selectRaw('supplier_id, tanggal_nota, SUM(jumlah) AS total')
            ->groupBy('supplier_id', 'tanggal_nota')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->supplier_id . '|' . substr((string) $r->tanggal_nota, 0, 10) => (float) $r->total]);

        $langsung = [];
        $bayarLangsung = [];
        foreach ($belanjaHari as $key => $belanja) {
            $id = (int) explode('|', $key)[0];
            $semua = $semuaKlienHari[$key] ?? $belanja;
            $langsung[$id] = ($langsung[$id] ?? 0) + $belanja;
            $bayarLangsung[$id] = ($bayarLangsung[$id] ?? 0) + min($semua, $bayarHari[$key] ?? 0) * ($semua > 0 ? $belanja / $semua : 0);
        }
        $langsung = collect($langsung);

        $bayarHutang = Pembayaran::query()
            ->whereNotNull('pembelian_stok_id')
            ->selectRaw('pembelian_stok_id, SUM(jumlah) AS dibayar')
            ->groupBy('pembelian_stok_id');

        $pembelian = PembelianStok::query()
            ->leftJoinSub($bayarHutang, 'bayar', 'bayar.pembelian_stok_id', '=', 'pembelian_stoks.id')
            ->whereBetween('pembelian_stoks.tanggal', [$dari, $sampai])
            ->when($supplierIds, fn ($q) => $q->whereIn('pembelian_stoks.supplier_id', $supplierIds))
            ->when($klienIds, fn ($q) => $q->whereRaw('1 = 0'))
            ->selectRaw("
                pembelian_stoks.supplier_id,
                SUM(pembelian_stoks.total) AS total,
                SUM(CASE WHEN pembelian_stoks.metode_bayar = 'tunai' THEN pembelian_stoks.total ELSE COALESCE(bayar.dibayar, 0) END) AS dibayar
            ")
            ->groupBy('pembelian_stoks.supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $ids = collect($langsung->keys())->merge($pembelian->keys())->unique();
        $suppliers = Supplier::whereIn('id', $ids)->pluck('nama', 'id');

        $rows = $ids->map(function ($id) use ($langsung, $bayarLangsung, $pembelian, $suppliers) {
            $belanja = (float) ($langsung[$id] ?? 0);
            $beliStok = (float) ($pembelian[$id]->total ?? 0);
            $dibayar = round((float) ($bayarLangsung[$id] ?? 0), 2) + (float) ($pembelian[$id]->dibayar ?? 0);

            return (object) [
                'nama' => $suppliers[$id] ?? '-',
                'belanja_langsung' => $belanja,
                'pembelian_stok' => $beliStok,
                'total' => $belanja + $beliStok,
                'dibayar' => $dibayar,
                'sisa' => max(0, $belanja + $beliStok - $dibayar),
            ];
        })->sortByDesc('total')->values();

        return [
            'rows' => $rows,
            'supplier' => count($supplierIds) === 1 ? Supplier::find($supplierIds[0]) : null,
            'total' => $this->jumlahkan($rows, ['belanja_langsung', 'pembelian_stok', 'total', 'dibayar', 'sisa']),
            'filterKlien' => (bool) $klienIds,
        ];
    }

    /**
     * Per barang: qty terjual (dari supplier vs dari stok), modal, jual, laba.
     * Bisa difilter klien & supplier (masing-masing lebih dari satu).
     */
    public function barang(string $dari, string $sampai, ?int $barangId = null, array $klienIds = [], array $supplierIds = []): array
    {
        $agregat = TransaksiDetail::query()
            ->join('transaksis', 'transaksis.id', '=', 'transaksi_details.transaksi_id')
            ->whereBetween('transaksis.tanggal', [$dari, $sampai])
            ->when($barangId, fn ($q) => $q->where('transaksi_details.barang_id', $barangId))
            ->when($klienIds, fn ($q) => $q->whereIn('transaksis.klien_id', $klienIds))
            ->when($supplierIds, fn ($q) => $q->whereIn('transaksi_details.supplier_id', $supplierIds))
            ->selectRaw("
                transaksi_details.barang_id,
                SUM(CASE WHEN transaksi_details.sumber = 'supplier' THEN transaksi_details.qty ELSE 0 END) AS qty_supplier,
                SUM(CASE WHEN transaksi_details.sumber = 'stok' THEN transaksi_details.qty ELSE 0 END) AS qty_stok,
                SUM(transaksi_details.qty) AS qty_total,
                SUM(transaksi_details.subtotal_beli) AS total_modal,
                SUM(transaksi_details.subtotal_jual) AS total_jual
            ")
            ->groupBy('transaksi_details.barang_id')
            ->get();

        $barangs = Barang::whereIn('id', $agregat->pluck('barang_id'))->get(['id', 'nama', 'satuan'])->keyBy('id');

        $rows = $agregat->map(fn ($r) => (object) [
            'nama' => $barangs[$r->barang_id]->nama ?? '-',
            'satuan' => $barangs[$r->barang_id]->satuan ?? '',
            'qty_supplier' => (float) $r->qty_supplier,
            'qty_stok' => (float) $r->qty_stok,
            'qty_total' => (float) $r->qty_total,
            'total_modal' => (float) $r->total_modal,
            'total_jual' => (float) $r->total_jual,
            'laba' => (float) $r->total_jual - (float) $r->total_modal,
        ])->sortByDesc('total_jual')->values();

        return [
            'rows' => $rows,
            'barang' => $barangId ? Barang::find($barangId) : null,
            'total' => $this->jumlahkan($rows, ['total_modal', 'total_jual', 'laba']),
        ];
    }

    /** Laporan stok: stok awal, masuk, keluar, penyesuaian, stok akhir, nilai stok akhir */
    public function stok(string $dari, string $sampai, array $supplierIds = []): array
    {
        $mutasi = DB::table('stok_mutasis')
            ->selectRaw("
                barang_id,
                SUM(CASE WHEN tanggal < ? THEN (CASE WHEN tipe = 'keluar' THEN -qty ELSE qty END) ELSE 0 END) AS stok_awal,
                SUM(CASE WHEN tanggal BETWEEN ? AND ? AND tipe = 'masuk' THEN qty ELSE 0 END) AS masuk,
                SUM(CASE WHEN tanggal BETWEEN ? AND ? AND tipe = 'keluar' THEN qty ELSE 0 END) AS keluar,
                SUM(CASE WHEN tanggal BETWEEN ? AND ? AND tipe = 'penyesuaian' THEN qty ELSE 0 END) AS penyesuaian
            ", [$dari, $dari, $sampai, $dari, $sampai, $dari, $sampai])
            ->where('tanggal', '<=', $sampai)
            ->groupBy('barang_id')
            ->get()
            ->keyBy('barang_id');

        $nilaiOpname = DB::table('stok_opnames')
            ->whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('barang_id, SUM(nilai_selisih) AS nilai')
            ->groupBy('barang_id')
            ->pluck('nilai', 'barang_id');

        // Filter supplier = supplier barang (bisa lebih dari satu)
        $rows = Barang::diStok()->when($supplierIds, fn ($q) => $q->whereIn('supplier_id', $supplierIds))->orderBy('nama')->get()->map(function ($b) use ($mutasi, $nilaiOpname) {
            $m = $mutasi[$b->id] ?? null;
            $awal = (float) ($m->stok_awal ?? 0);
            $masuk = (float) ($m->masuk ?? 0);
            $keluar = (float) ($m->keluar ?? 0);
            $penyesuaian = (float) ($m->penyesuaian ?? 0);
            $akhir = $awal + $masuk - $keluar + $penyesuaian;

            return (object) [
                'nama' => $b->nama,
                'satuan' => $b->satuan,
                'stok_awal' => $awal,
                'masuk' => $masuk,
                'keluar' => $keluar,
                'penyesuaian' => $penyesuaian,
                'stok_akhir' => $akhir,
                'hpp' => (float) $b->hpp,
                // Nilai stok akhir memakai HPP terkini
                'nilai_akhir' => round($akhir * (float) $b->hpp, 2),
                'nilai_penyesuaian' => (float) ($nilaiOpname[$b->id] ?? 0),
            ];
        });

        return [
            'rows' => $rows,
            'total' => $this->jumlahkan($rows, ['nilai_akhir', 'nilai_penyesuaian']),
        ];
    }

    private function jumlahkan(Collection $rows, array $kolom): array
    {
        return collect($kolom)->mapWithKeys(fn ($k) => [$k => $rows->sum($k)])->all();
    }
}
