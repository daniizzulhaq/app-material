<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\StokOpnameRequest;
use App\Models\Barang;
use App\Models\StokOpname;
use App\Services\StokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StokOpnameController extends Controller
{
    public function __construct(private StokService $stok)
    {
    }

    public function index(Request $request): View
    {
        $query = StokOpname::query()
            ->with('barang')
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->sampai))
            ->when($request->filled('barang_id'), fn ($q) => $q->where('barang_id', $request->barang_id));

        $totalNilai = (clone $query)->sum('nilai_selisih');
        $opnames = $query->latest('tanggal')->latest('id')->paginate(15)->withQueryString();
        $barangs = Barang::orderBy('nama')->get(['id', 'nama']);

        return view('stok-opname.index', compact('opnames', 'barangs', 'totalNilai'));
    }

    public function create(Request $request): View
    {
        $barangs = Barang::orderBy('nama')->get(['id', 'nama', 'satuan', 'stok', 'hpp']);

        return view('stok-opname.form', [
            'barangs' => $barangs,
            'barangTerpilih' => $request->integer('barang_id') ?: null,
        ]);
    }

    public function store(StokOpnameRequest $request): RedirectResponse
    {
        $opname = $this->stok->simpanOpname($request->validated());
        $opname->load('barang');

        $pesan = (float) $opname->selisih == 0
            ? "Stok {$opname->barang->nama} sesuai, tidak ada selisih."
            : "Opname {$opname->barang->nama} disimpan. Selisih " . qty($opname->selisih) . " {$opname->barang->satuan} (" . rupiah($opname->nilai_selisih) . ').';

        return redirect()->route('stok-opname.index')->with('success', $pesan);
    }

    public function destroy(StokOpname $stokOpname, RiwayatService $riwayat): RedirectResponse
    {
        $riwayat->hapus($stokOpname, fn () => $this->stok->hapusOpname($stokOpname));

        return redirect()->route('stok-opname.index')->with('success', 'Data opname dibatalkan dan stok dikembalikan.');
    }
}
