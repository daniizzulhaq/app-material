<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\TransaksiRequest;
use App\Models\Barang;
use App\Models\Klien;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Services\TransaksiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Rekapan / Nota Penjualan */
class TransaksiController extends Controller
{
    public function __construct(private TransaksiService $service)
    {
    }

    public function index(Request $request): View
    {
        $query = Transaksi::query()
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->sampai))
            ->when($request->filled('klien_id'), fn ($q) => $q->where('klien_id', $request->klien_id))
            ->when(array_key_exists((string) $request->kategori, Klien::KATEGORI),
                fn ($q) => $q->whereIn('klien_id', Klien::kategori($request->kategori)->select('id')))
            ->when($request->filled('status_bayar'), fn ($q) => $q->where('status_bayar', $request->status_bayar))
            ->when($request->filled('q'), fn ($q) => $q->where('no_nota', 'like', "%{$request->q}%"))
            // Filter berdasarkan isi item nota
            ->when($request->filled('supplier_id') || $request->filled('barang_id') || $request->filled('sumber'), function ($q) use ($request) {
                $q->whereHas('details', function ($d) use ($request) {
                    $d->when($request->filled('supplier_id'), fn ($x) => $x->where('supplier_id', $request->supplier_id))
                        ->when($request->filled('barang_id'), fn ($x) => $x->where('barang_id', $request->barang_id))
                        ->when($request->filled('sumber'), fn ($x) => $x->where('sumber', $request->sumber));
                });
            });

        $ringkasan = (clone $query)
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(total_jual),0) AS jual, COALESCE(SUM(total_modal),0) AS modal, COALESCE(SUM(laba),0) AS laba')
            ->first();

        $transaksis = $query->with('klien')->withCount('details')->withSum('pembayarans', 'jumlah')
            ->latest('tanggal')->latest('id')->paginate(15)->withQueryString();

        return view('transaksi.index', [
            'transaksis' => $transaksis,
            'ringkasan' => $ringkasan,
            'kliens' => Klien::kategori($request->kategori)->orderBy('nama')->get(['id', 'nama']),
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'barangs' => Barang::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function create(): View
    {
        return view('transaksi.form', $this->dataForm(new Transaksi(['tanggal' => now()])));
    }

    public function store(TransaksiRequest $request): RedirectResponse
    {
        $transaksi = $this->service->simpan($request->validated());

        return redirect()->route('transaksi.show', $transaksi)->with('success', "Nota {$transaksi->no_nota} berhasil disimpan.");
    }

    public function show(Transaksi $transaksi): View
    {
        $transaksi->load(['klien', 'details.barang', 'details.supplier', 'pembayarans']);

        return view('transaksi.show', compact('transaksi'));
    }

    public function edit(Transaksi $transaksi): View
    {
        $transaksi->load('details');

        return view('transaksi.form', $this->dataForm($transaksi));
    }

    public function update(TransaksiRequest $request, Transaksi $transaksi): RedirectResponse
    {
        $this->service->simpan($request->validated(), $transaksi);

        return redirect()->route('transaksi.show', $transaksi)->with('success', "Nota {$transaksi->no_nota} berhasil diperbarui.");
    }

    public function destroy(Transaksi $transaksi, RiwayatService $riwayat): RedirectResponse
    {
        $riwayat->hapus($transaksi, fn () => $this->service->hapus($transaksi));

        return redirect()->route('transaksi.index')->with('success', "Nota {$transaksi->no_nota} berhasil dihapus.");
    }

    /** Cetak nota: tampilan cetak browser, atau unduh PDF dengan ?pdf=1 */
    public function cetak(Request $request, Transaksi $transaksi): Response|View
    {
        $transaksi->load(['klien', 'details.barang']);
        $transaksi->loadSum('pembayarans', 'jumlah');

        if ($request->boolean('pdf')) {
            return Pdf::loadView('transaksi.cetak', ['transaksi' => $transaksi, 'pdf' => true])
                ->setPaper('a5', 'landscape')
                ->download("Nota-{$transaksi->no_nota}.pdf");
        }

        return view('transaksi.cetak', ['transaksi' => $transaksi, 'pdf' => false]);
    }

    private function dataForm(Transaksi $transaksi): array
    {
        $items = old('items', $transaksi->exists
            ? $transaksi->details->map(fn ($d) => $d->only(['barang_id', 'sumber', 'supplier_id', 'qty', 'harga_beli', 'harga_jual']))->all()
            : []);

        // Stok tersedia saat edit = stok sekarang + qty yang dipakai nota ini
        $qtyNotaIni = $transaksi->exists
            ? $transaksi->details->where('sumber', 'stok')->groupBy('barang_id')->map(fn ($g) => (float) $g->sum('qty'))
            : collect();

        $barangs = Barang::orderBy('nama')->get(['id', 'nama', 'supplier_id', 'satuan', 'stok', 'hpp', 'harga_jual_default'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'label' => $b->nama,
                'supplier_id' => $b->supplier_id, // supplier langganan → auto-isi kolom supplier
                'satuan' => $b->satuan,
                'stok' => round((float) $b->stok + ($qtyNotaIni[$b->id] ?? 0), 2),
                'hpp' => (float) $b->hpp,
                'harga_jual' => (float) $b->harga_jual_default,
            ]);

        return [
            'transaksi' => $transaksi,
            'items' => $items, // indeks asli dipertahankan agar error validasi cocok per baris
            'kliens' => Klien::orderBy('nama')->get(['id', 'nama', 'kategori']),
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'barangs' => $barangs,
            // HPP terkunci milik nota ini (agar tampilan edit sesuai perhitungan server)
            'hppTerkunci' => $transaksi->exists ? $transaksi->details->where('sumber', 'stok')->pluck('harga_beli', 'barang_id') : collect(),
        ];
    }
}
