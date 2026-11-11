<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\CashflowRequest;
use App\Models\Cashflow;
use App\Services\CashflowService;
use App\Services\ModalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashflowController extends Controller
{
    public function __construct(private CashflowService $service)
    {
    }

    public function index(Request $request): View
    {
        $dari = $request->input('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->input('sampai', now()->toDateString());

        // Saldo dihitung dari seluruh kategori agar saldo kas tetap benar
        $saldoAwal = $this->service->saldoSebelum($dari);

        $periode = Cashflow::query()
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);

        $totalPeriode = (clone $periode)->selectRaw("
            COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN nominal ELSE 0 END), 0) AS keluar
        ")->first();

        $saldoAkhir = $saldoAwal + (float) $totalPeriode->masuk - (float) $totalPeriode->keluar;

        // Daftar (boleh difilter kategori/tipe)
        $filter = (clone $periode)
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori', $request->kategori))
            ->when($request->filled('tipe'), fn ($q) => $q->where('tipe', $request->tipe));

        $totalFilter = (clone $filter)->selectRaw("
            COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN nominal ELSE 0 END), 0) AS keluar
        ")->first();

        $cashflows = $filter->orderBy('tanggal')->orderBy('id')->paginate(25)->withQueryString();

        // Perputaran modal: modal − total nota beli per hari + bagian beli yang dibayar klien
        $modal = app(ModalService::class)->ringkasan($dari, $sampai);

        return view('cashflow.index', compact('cashflows', 'saldoAwal', 'saldoAkhir', 'totalPeriode', 'totalFilter', 'dari', 'sampai', 'modal'));
    }

    public function create(Request $request): View
    {
        // ?kategori=modal → tombol "Input Modal Awal"
        $modal = $request->input('kategori') === 'modal';

        return view('cashflow.form', ['cashflow' => new Cashflow([
            'tanggal' => now(),
            'tipe' => $modal ? 'masuk' : 'keluar',
            'kategori' => $modal ? 'modal' : 'operasional',
            'keterangan' => $modal ? 'Modal awal' : null,
        ])]);
    }

    public function store(CashflowRequest $request): RedirectResponse
    {
        Cashflow::create($request->validated());

        return redirect()->route('cashflow.index')->with('success', 'Cashflow manual berhasil ditambahkan.');
    }

    public function edit(Cashflow $cashflow): View|RedirectResponse
    {
        if ($cashflow->otomatis) {
            return redirect()->route('cashflow.index')->with('error', 'Baris otomatis tidak bisa diedit manual. Ubah dari dokumen sumbernya.');
        }

        return view('cashflow.form', compact('cashflow'));
    }

    public function update(CashflowRequest $request, Cashflow $cashflow): RedirectResponse
    {
        abort_if($cashflow->otomatis, 403, 'Baris otomatis tidak bisa diedit manual.');

        $cashflow->update($request->validated());

        return redirect()->route('cashflow.index')->with('success', 'Cashflow berhasil diperbarui.');
    }

    public function destroy(Cashflow $cashflow, RiwayatService $riwayat): RedirectResponse
    {
        if ($cashflow->otomatis) {
            return back()->with('error', 'Baris otomatis tidak bisa dihapus manual. Hapus dari dokumen sumbernya.');
        }

        $riwayat->hapus($cashflow, fn () => $cashflow->delete());

        return redirect()->route('cashflow.index')->with('success', 'Cashflow berhasil dihapus.');
    }
}
