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
     * Per klien. Jika klien dipilih → daftar nota klien tsb; jika tidak → rekap per klien.
     */
    public function klien(string $dari, string $sampai, ?int $klienId = null): array
    {
        $base = Transaksi::query()
            ->leftJoinSub($this->bayarPerNota(), 'bayar', 'bayar.transaksi_id', '=', 'transaksis.id')
            ->whereBetween('transaksis.tanggal', [$dari, $sampai])
            ->when($klienId, fn ($q) => $q->where('transaksis.klien_id', $klienId));

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

            $kliens = Klien::whereIn('id', $agregat->pluck('klien_id'))->pluck('nama', 'id');

            $rows = $agregat->map(fn ($r) => (object) [
                'nama' => $kliens[$r->klien_id] ?? '-',
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
            'total' => $this->jumlahkan($rows, ['jumlah_nota', 'total_jual', 'dibayar', 'sisa', 'laba']),
        ];
    }

    /**
     * Per supplier: belanja langsung (item nota sumber supplier) + pembelian stok.
     * Belanja langsung dianggap lunas di hari itu (tercatat di cashflow belanja_supplier).
     */
    public function supplier(string $dari, string $sampai, ?int $supplierId = null): array
    {
        $langsung = TransaksiDetail::query()
            ->join('transaksis', 'transaksis.id', '=', 'transaksi_details.transaksi_id')
            ->where('transaksi_details.sumber', 'supplier')
            ->whereBetween('transaksis.tanggal', [$dari, $sampai])
            ->when($supplierId, fn ($q) => $q->where('transaksi_details.supplier_id', $supplierId))
            ->selectRaw('transaksi_details.supplier_id, SUM(transaksi_details.subtotal_beli) AS total')
            ->groupBy('transaksi_details.supplier_id')
            ->pluck('total', 'supplier_id');

        $bayarHutang = Pembayaran::query()
            ->whereNotNull('pembelian_stok_id')
            ->selectRaw('pembelian_stok_id, SUM(jumlah) AS dibayar')
            ->groupBy('pembelian_stok_id');

        $pembelian = PembelianStok::query()
            ->leftJoinSub($bayarHutang, 'bayar', 'bayar.pembelian_stok_id', '=', 'pembelian_stoks.id')
            ->whereBetween('pembelian_stoks.tanggal', [$dari, $sampai])
            ->when($supplierId, fn ($q) => $q->where('pembelian_stoks.supplier_id', $supplierId))
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

        $rows = $ids->map(function ($id) use ($langsung, $pembelian, $suppliers) {
            $belanja = (float) ($langsung[$id] ?? 0);
            $beliStok = (float) ($pembelian[$id]->total ?? 0);
            $dibayar = $belanja + (float) ($pembelian[$id]->dibayar ?? 0);

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
            'supplier' => $supplierId ? Supplier::find($supplierId) : null,
            'total' => $this->jumlahkan($rows, ['belanja_langsung', 'pembelian_stok', 'total', 'dibayar', 'sisa']),
        ];
    }

    /** Per barang: qty terjual (dari supplier vs dari stok), modal, jual, laba */
    public function barang(string $dari, string $sampai, ?int $barangId = null): array
    {
        $agregat = TransaksiDetail::query()
            ->join('transaksis', 'transaksis.id', '=', 'transaksi_details.transaksi_id')
            ->whereBetween('transaksis.tanggal', [$dari, $sampai])
            ->when($barangId, fn ($q) => $q->where('transaksi_details.barang_id', $barangId))
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
    public function stok(string $dari, string $sampai): array
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

        $rows = Barang::orderBy('nama')->get()->map(function ($b) use ($mutasi, $nilaiOpname) {
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
