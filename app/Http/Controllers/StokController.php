<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StokController extends Controller
{
    /** Daftar stok + nilai stok */
    public function index(Request $request): View
    {
        // Hanya barang yang sudah masuk stok lewat pembelian stok (bukan semua master barang)
        $query = Barang::diStok()
            ->when($request->filled('q'), fn ($q) => $q->where('nama', 'like', "%{$request->q}%"))
            ->when($request->boolean('menipis'), fn ($q) => $q->menipis());

        $ringkasan = (clone $query)->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(stok * hpp), 0) AS nilai')->first();
        $jumlahMenipis = Barang::menipis()->count();

        $barangs = $query->orderBy('nama')->paginate(20)->withQueryString();

        return view('stok.index', compact('barangs', 'ringkasan', 'jumlahMenipis'));
    }

    /** Kartu stok per barang (riwayat mutasi + saldo berjalan) */
    public function show(Request $request, Barang $barang): View
    {
        $dari = $request->input('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->input('sampai', now()->toDateString());

        // Saldo awal = stok sesudah mutasi terakhir sebelum periode
        $saldoAwal = (float) $barang->mutasis()
            ->whereDate('tanggal', '<', $dari)
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->value('stok_sesudah');

        $mutasis = $barang->mutasis()
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->orderBy('tanggal')->orderBy('id')
            ->get();

        return view('stok.show', compact('barang', 'mutasis', 'saldoAwal', 'dari', 'sampai'));
    }
}
