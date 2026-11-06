<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\BarangRequest;
use App\Models\Barang;
use App\Models\StokMutasi;
use App\Services\StokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BarangController extends Controller
{
    public function __construct(private StokService $stok)
    {
    }

    public function index(Request $request): View
    {
        $barangs = Barang::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(fn ($w) => $w->where('nama', 'like', "%{$request->q}%"));
            })
            ->when($request->filled('satuan'), fn ($q) => $q->where('satuan', $request->satuan))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        $satuans = Barang::distinct()->orderBy('satuan')->pluck('satuan');

        return view('barang.index', compact('barangs', 'satuans'));
    }

    public function create(): View
    {
        $barang = new Barang();

        return view('barang.form', compact('barang'));
    }

    public function store(BarangRequest $request): RedirectResponse
    {
        $barang = DB::transaction(function () use ($request) {
            $barang = Barang::create($request->safe()->except(['stok_awal', 'hpp_awal']));
            // Stok awal dicatat sebagai mutasi masuk agar kartu stok & HPP konsisten
            $this->stok->stokAwal($barang, (float) $request->stok_awal, (float) $request->hpp_awal);

            return $barang;
        });

        return redirect()->route('barang.index')->with('success', "Barang {$barang->nama} berhasil ditambahkan.");
    }

    public function edit(Barang $barang): View
    {
        return view('barang.form', compact('barang'));
    }

    public function update(BarangRequest $request, Barang $barang): RedirectResponse
    {
        $barang->update($request->validated());

        return redirect()->route('barang.index')->with('success', "Barang {$barang->nama} berhasil diperbarui.");
    }

    public function destroy(Barang $barang, RiwayatService $riwayat): RedirectResponse
    {
        if ($barang->sudahDipakai()) {
            return back()->with('error', "Barang {$barang->nama} tidak bisa dihapus karena sudah dipakai di pembelian, nota, atau stok opname.");
        }

        $riwayat->hapus($barang, function () use ($barang) {
            StokMutasi::where('barang_id', $barang->id)->where('referensi_tipe', StokMutasi::REF_STOK_AWAL)->delete();
            $barang->delete();
        });

        return redirect()->route('barang.index')->with('success', "Barang {$barang->nama} berhasil dihapus.");
    }
}
