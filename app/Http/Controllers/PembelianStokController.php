<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\PembelianStokRequest;
use App\Models\Barang;
use App\Models\PembelianStok;
use App\Models\Supplier;
use App\Services\PembelianStokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembelianStokController extends Controller
{
    public function __construct(private PembelianStokService $service)
    {
    }

    public function index(Request $request): View
    {
        $query = PembelianStok::query()
            ->with('supplier')
            ->withCount('details')
            ->withSum('pembayarans', 'jumlah')
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->sampai))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->filled('metode_bayar'), fn ($q) => $q->where('metode_bayar', $request->metode_bayar))
            ->when($request->filled('status_bayar'), fn ($q) => $q->where('status_bayar', $request->status_bayar))
            ->when($request->filled('q'), fn ($q) => $q->where('no_faktur', 'like', "%{$request->q}%"));

        $totalFilter = (clone $query)->sum('total');
        $pembelians = $query->latest('tanggal')->latest('id')->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('nama')->get(['id', 'nama']);

        return view('pembelian-stok.index', compact('pembelians', 'suppliers', 'totalFilter'));
    }

    public function create(): View
    {
        return view('pembelian-stok.form', $this->dataForm(new PembelianStok(['tanggal' => now(), 'metode_bayar' => 'tunai'])));
    }

    public function store(PembelianStokRequest $request): RedirectResponse
    {
        $pembelian = $this->service->simpan($request->validated());

        return redirect()->route('pembelian-stok.show', $pembelian)->with('success', "Pembelian {$pembelian->no_faktur} berhasil disimpan. Stok & HPP diperbarui.");
    }

    public function show(PembelianStok $pembelianStok): View
    {
        $pembelianStok->load(['supplier', 'details.barang', 'pembayarans']);

        return view('pembelian-stok.show', ['pembelian' => $pembelianStok]);
    }

    public function edit(PembelianStok $pembelianStok): View
    {
        $pembelianStok->load('details');

        return view('pembelian-stok.form', $this->dataForm($pembelianStok));
    }

    public function update(PembelianStokRequest $request, PembelianStok $pembelianStok): RedirectResponse
    {
        $this->service->simpan($request->validated(), $pembelianStok);

        return redirect()->route('pembelian-stok.show', $pembelianStok)->with('success', "Pembelian {$pembelianStok->no_faktur} berhasil diperbarui.");
    }

    public function destroy(PembelianStok $pembelianStok, RiwayatService $riwayat): RedirectResponse
    {
        $riwayat->hapus($pembelianStok, fn () => $this->service->hapus($pembelianStok));

        return redirect()->route('pembelian-stok.index')->with('success', "Pembelian {$pembelianStok->no_faktur} berhasil dihapus. Stok dikembalikan.");
    }

    private function dataForm(PembelianStok $pembelian): array
    {
        // Baris item: dari old() bila validasi gagal, dari detail bila edit, atau 1 baris kosong
        $items = old('items', $pembelian->exists
            ? $pembelian->details->map(fn ($d) => $d->only(['barang_id', 'qty', 'harga_beli']))->all()
            : [['barang_id' => '', 'qty' => '', 'harga_beli' => '']]);

        return [
            'pembelian' => $pembelian,
            'items' => $items, // indeks asli dipertahankan agar error validasi cocok per baris
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'barangs' => Barang::orderBy('nama')->get(['id', 'nama', 'satuan', 'stok', 'hpp']),
        ];
    }
}
