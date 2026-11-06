<?php

namespace App\Http\Controllers;

use App\Models\RiwayatHapus;
use App\Services\RiwayatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'terhapus');

        $riwayats = RiwayatHapus::query()
            ->with('user:id,name')
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('label', 'like', "%{$request->q}%")
                ->orWhere('ringkasan', 'like', "%{$request->q}%")))
            ->when($status === 'terhapus', fn ($q) => $q->whereNull('dipulihkan_at'))
            ->when($status === 'dipulihkan', fn ($q) => $q->whereNotNull('dipulihkan_at'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $jumlahTerhapus = RiwayatHapus::whereNull('dipulihkan_at')->count();

        return view('riwayat.index', compact('riwayats', 'status', 'jumlahTerhapus'));
    }

    public function pulihkan(RiwayatHapus $riwayat, RiwayatService $service): RedirectResponse
    {
        $pesan = $service->pulihkan($riwayat);

        return back()->with('success', $pesan);
    }

    /** Hapus permanen: snapshot dibuang, data tidak bisa dipulihkan lagi. */
    public function destroy(RiwayatHapus $riwayat): RedirectResponse
    {
        $riwayat->delete();

        return back()->with('success', "Riwayat {$riwayat->label} dihapus permanen.");
    }

    public function kosongkan(): RedirectResponse
    {
        $jumlah = RiwayatHapus::whereNotNull('dipulihkan_at')->delete();

        return back()->with('success', "{$jumlah} riwayat yang sudah dipulihkan dibersihkan.");
    }
}
