<?php

namespace App\Http\Controllers;

use App\Services\RiwayatService;
use App\Http\Requests\PembayaranRequest;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Services\TagihanSupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cashflow & status bayar diperbarui otomatis oleh PembayaranObserver.
 */
class PembayaranController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pembayaran::query()
            ->with(['klien', 'supplier', 'transaksi', 'pembelianStok'])
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->sampai))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis))
            ->when(ids($request->input('supplier_id')), fn ($q, $ids) => $q->whereIn('supplier_id', $ids))
            ->when($request->filled('metode'), fn ($q) => $q->where('metode', $request->metode));

        $ringkasan = (clone $query)->selectRaw("
            COALESCE(SUM(CASE WHEN jenis = 'klien' THEN jumlah ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN jenis = 'supplier' THEN jumlah ELSE 0 END), 0) AS keluar
        ")->first();

        $pembayarans = $query->latest('tanggal')->latest('id')->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('nama')->get(['id', 'nama']);

        return view('pembayaran.index', compact('pembayarans', 'ringkasan', 'suppliers'));
    }

    public function create(Request $request): View
    {
        $pembayaran = new Pembayaran([
            'tanggal' => now(),
            'jenis' => $request->input('jenis', 'klien'),
            'transaksi_id' => $request->input('transaksi_id'),
            'pembelian_stok_id' => $request->input('pembelian_stok_id'),
            'supplier_id' => $request->input('supplier_id'),
            'metode' => 'tunai',
        ]);
        if ($pembayaran->pembelian_stok_id && ! $pembayaran->supplier_id) {
            $pembayaran->supplier_id = PembelianStok::whereKey($pembayaran->pembelian_stok_id)->value('supplier_id');
        }

        return view('pembayaran.form', $this->dataForm($pembayaran));
    }

    public function store(PembayaranRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            Pembayaran::create($this->lengkapiData($request->validated()));
        });

        return redirect()->route('pembayaran.index')->with('success', 'Pembayaran berhasil dicatat. Cashflow & status tagihan diperbarui.');
    }

    public function edit(Pembayaran $pembayaran): View
    {
        return view('pembayaran.form', $this->dataForm($pembayaran));
    }

    public function update(PembayaranRequest $request, Pembayaran $pembayaran): RedirectResponse
    {
        DB::transaction(function () use ($request, $pembayaran) {
            $pembayaran->update($this->lengkapiData($request->validated()));
        });

        return redirect()->route('pembayaran.index')->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function destroy(Pembayaran $pembayaran, RiwayatService $riwayat): RedirectResponse
    {
        $riwayat->hapus($pembayaran, fn () => $pembayaran->delete());

        return redirect()->route('pembayaran.index')->with('success', 'Pembayaran berhasil dihapus. Cashflow & status tagihan diperbarui.');
    }

    /**
     * JSON pilihan tagihan supplier untuk dropdown:
     * belanja nota per tanggal (semua klien digabung) + faktur pembelian stok hutang.
     * GET pembayaran/tagihan-supplier?supplier_id=3&pembayaran=ID&selected=tanggal:2026-11-06
     */
    public function tagihanSupplier(Request $request, TagihanSupplierService $tagihan): JsonResponse
    {
        $supplierId = $request->integer('supplier_id');
        $pembayaran = Pembayaran::find($request->integer('pembayaran'));
        $pilih = (string) $request->input('selected');

        $nota = $tagihan->perTanggal($supplierId, $pembayaran?->id)
            ->filter(fn ($r) => $r->sisa > 0 || $pilih === 'tanggal:' . $r->tanggal)
            ->map(fn ($r) => [
                'value' => 'tanggal:' . $r->tanggal,
                'text' => 'Nota ' . tanggal($r->tanggal) . ' · ' . $r->nota . ' nota · belanja ' . rupiah($r->total) . ' · sisa ' . rupiah($r->sisa),
                'total' => $r->total,
                'sisa' => $r->sisa,
                'optgroup' => 'nota',
            ])->values();

        $pilihFaktur = str_starts_with($pilih, 'faktur:') ? (int) substr($pilih, 7) : null;
        $faktur = $tagihan->faktur($supplierId, $pilihFaktur, $pembayaran)
            ->map(fn ($f) => [
                'value' => 'faktur:' . $f->id,
                'text' => $f->no_faktur . ' · ' . tanggal($f->tanggal) . ' · total ' . rupiah($f->total) . ' · sisa ' . rupiah($f->sisa),
                'total' => $f->total,
                'sisa' => $f->sisa,
                'optgroup' => 'faktur',
            ]);

        return response()->json($nota->concat($faktur)->values());
    }

    /** klien_id / supplier_id diambil dari tagihan yang dipilih */
    private function lengkapiData(array $data): array
    {
        if ($data['jenis'] === 'klien') {
            $transaksi = Transaksi::lockForUpdate()->findOrFail($data['transaksi_id']);
            $data['klien_id'] = $transaksi->klien_id;
            $data['supplier_id'] = null;
            $data['pembelian_stok_id'] = null;
            $data['tanggal_nota'] = null;
        } elseif (! empty($data['pembelian_stok_id'])) {
            $pembelian = PembelianStok::lockForUpdate()->findOrFail($data['pembelian_stok_id']);
            $data['supplier_id'] = $pembelian->supplier_id;
            $data['klien_id'] = null;
            $data['transaksi_id'] = null;
            $data['tanggal_nota'] = null;
        } else {
            // Belanja nota supplier per tanggal
            $data['klien_id'] = null;
            $data['transaksi_id'] = null;
            $data['pembelian_stok_id'] = null;
        }

        return $data;
    }

    private function dataForm(Pembayaran $pembayaran): array
    {
        // Tagihan yang masih punya sisa (+ tagihan milik pembayaran yang sedang diedit)
        $notas = Transaksi::with('klien')->withSum('pembayarans', 'jumlah')
            ->where(fn ($q) => $q->where('status_bayar', '!=', 'lunas')->orWhere('id', $pembayaran->transaksi_id))
            ->latest('tanggal')->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'label' => "{$t->no_nota} · {$t->klien->nama} · " . tanggal($t->tanggal),
                'total' => (float) $t->total_jual,
                'sisa' => $t->sisaPiutang() + ($pembayaran->transaksi_id == $t->id ? (float) $pembayaran->jumlah : 0),
            ]);

        $suppliers = Supplier::orderBy('nama')->get(['id', 'nama']);

        return compact('pembayaran', 'notas', 'suppliers');
    }
}
