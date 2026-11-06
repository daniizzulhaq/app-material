<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(fn ($w) => $w->where('nama', 'like', "%{$request->q}%")
                    ->orWhere('no_hp', 'like', "%{$request->q}%")
                    ->orWhere('alamat', 'like', "%{$request->q}%"));
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('supplier.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('supplier.form', ['supplier' => new Supplier()]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()->route('supplier.index')->with('success', "Supplier {$supplier->nama} berhasil ditambahkan.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('supplier.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('supplier.index')->with('success', "Supplier {$supplier->nama} berhasil diperbarui.");
    }

    public function destroy(Supplier $supplier, RiwayatService $riwayat): RedirectResponse
    {
        if ($supplier->sudahDipakai()) {
            return back()->with('error', "Supplier {$supplier->nama} tidak bisa dihapus karena sudah dipakai di pembelian, nota, atau pembayaran.");
        }

        $riwayat->hapus($supplier, fn () => $supplier->delete());

        return redirect()->route('supplier.index')->with('success', "Supplier {$supplier->nama} berhasil dihapus.");
    }
}
