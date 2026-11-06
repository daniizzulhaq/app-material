<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\PembelianStok;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Services\LaporanService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private LaporanService $laporan)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        // Default periode: bulan ini
        $dari = $request->input('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->input('sampai', now()->toDateString());

        $nota = Transaksi::whereBetween('tanggal', [$dari, $sampai])->selectRaw('
            COUNT(*) AS jumlah, COALESCE(SUM(total_qty),0) AS qty, COALESCE(SUM(total_jual),0) AS jual,
            COALESCE(SUM(total_modal),0) AS modal, COALESCE(SUM(laba),0) AS laba,
            COALESCE(SUM(total_belanja_supplier),0) AS belanja_supplier
        ')->first();

        $pembelianStok = (float) PembelianStok::whereBetween('tanggal', [$dari, $sampai])->sum('total');
        $nilaiStok = (float) Barang::sum(DB::raw('stok * hpp'));

        // Top 3 (memakai query laporan agar angkanya konsisten)
        $topSupplier = $this->laporan->supplier($dari, $sampai)['rows']->take(3);
        $topBarang = $this->laporan->barang($dari, $sampai)['rows']->take(3);
        $topKlien = $this->laporan->klien($dari, $sampai)['rows']->take(3);

        $transaksiTerakhir = Transaksi::with('klien')->latest('tanggal')->latest('id')->take(5)->get();
        $stokMenipis = Barang::menipis()->orderByRaw('stok - stok_minimum')->take(8)->get();

        // Grafik penjualan vs laba per hari
        $perHari = Transaksi::whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('tanggal, SUM(total_jual) AS jual, SUM(laba) AS laba')
            ->groupBy('tanggal')->get()
            ->keyBy(fn ($r) => $r->tanggal->toDateString());

        $grafik = ['label' => [], 'jual' => [], 'laba' => []];
        foreach (CarbonPeriod::create($dari, $sampai) as $hari) {
            $key = $hari->toDateString();
            $grafik['label'][] = $hari->translatedFormat('d M');
            $grafik['jual'][] = (float) ($perHari[$key]->jual ?? 0);
            $grafik['laba'][] = (float) ($perHari[$key]->laba ?? 0);
        }

        return view('dashboard', compact(
            'dari', 'sampai', 'nota', 'pembelianStok', 'nilaiStok',
            'topSupplier', 'topBarang', 'topKlien', 'transaksiTerakhir', 'stokMenipis', 'grafik'
        ));
    }
}
