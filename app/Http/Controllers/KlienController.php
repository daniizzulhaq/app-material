<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\KlienRequest;
use App\Models\Klien;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KlienController extends Controller
{
    public function index(Request $request): View
    {
        $kliens = Klien::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(fn ($w) => $w->where('nama', 'like', "%{$request->q}%")
                    ->orWhere('no_hp', 'like', "%{$request->q}%")
                    ->orWhere('alamat', 'like', "%{$request->q}%"));
            })
            ->kategori($request->kategori)
            ->withCount('transaksis')
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        $jumlahPerKategori = Klien::selectRaw('kategori, COUNT(*) AS jumlah')->groupBy('kategori')->pluck('jumlah', 'kategori');

        return view('klien.index', compact('kliens', 'jumlahPerKategori'));
    }

    public function create(): View
    {
        return view('klien.form', ['klien' => new Klien()]);
    }

    public function store(KlienRequest $request): RedirectResponse
    {
        $klien = Klien::create($request->validated());

        return redirect()->route('klien.index')->with('success', "Klien {$klien->nama} berhasil ditambahkan.");
    }

    public function edit(Klien $klien): View
    {
        return view('klien.form', compact('klien'));
    }

    public function update(KlienRequest $request, Klien $klien): RedirectResponse
    {
        $klien->update($request->validated());

        return redirect()->route('klien.index')->with('success', "Klien {$klien->nama} berhasil diperbarui.");
    }

    public function destroy(Klien $klien, RiwayatService $riwayat): RedirectResponse
    {
        if ($klien->sudahDipakai()) {
            return back()->with('error', "Klien {$klien->nama} tidak bisa dihapus karena sudah memiliki nota atau pembayaran.");
        }

        $riwayat->hapus($klien, fn () => $klien->delete());

        return redirect()->route('klien.index')->with('success', "Klien {$klien->nama} berhasil dihapus.");
    }
}
