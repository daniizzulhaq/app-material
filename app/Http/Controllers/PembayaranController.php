<?php

namespace App\Http\Controllers;

use App\Http\Requests\PembayaranRequest;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Transaksi;
use App\Services\RiwayatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Menu Pembayaran = uang masuk dari klien, bisa dikaitkan ke nota (per nota).
 * Belanja supplier dari nota lunas otomatis per hari (cashflow harian), jadi tidak ada pembayaran ke supplier di sini.
 * Pengecualian: pelunasan hutang pembelian stok, dibuka dari halaman Pembelian Stok (?pembelian_stok_id=).
 * Cashflow & status bayar diperbarui otomatis oleh PembayaranObserver.
 */
class PembayaranController extends Controller
{
    public function index(Request $request): View
    {
        $klienIds = ids($request->input('klien_id'));

        $query = Pembayaran::query()
            ->where('jenis', 'klien')
            ->with(['klien', 'transaksi.klien'])
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->sampai))
            ->when($klienIds, fn ($q) => $q->whereIn('klien_id', $klienIds))
            ->when($request->filled('metode'), fn ($q) => $q->where('metode', $request->metode));

        $ringkasan = (clone $query)->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(jumlah), 0) AS masuk')->first();

        $pembayarans = $query->latest('tanggal')->latest('id')->paginate(15)->withQueryString();
        $kliens = Klien::orderBy('nama')->get(['id', 'nama', 'kategori']);

        return view('pembayaran.index', compact('pembayarans', 'ringkasan', 'kliens', 'klienIds'));
    }

    public function create(Request $request): View
    {
        $pembayaran = new Pembayaran(['tanggal' => now(), 'jenis' => 'klien', 'metode' => 'tunai']);

        // Dari halaman Pembelian Stok: bayar hutang faktur
        if ($pembelian = PembelianStok::find($request->integer('pembelian_stok_id'))) {
            $pembayaran->fill(['jenis' => 'supplier', 'pembelian_stok_id' => $pembelian->id, 'supplier_id' => $pembelian->supplier_id]);
        }

        // Dari halaman detail nota: ?transaksi_id=ID
        if ($nota = Transaksi::find($request->integer('transaksi_id'))) {
            $pembayaran->fill(['klien_id' => $nota->klien_id, 'transaksi_id' => $nota->id]);
        }

        return view('pembayaran.form', $this->dataForm($pembayaran));
    }

    public function store(PembayaranRequest $request): RedirectResponse
    {
        $pembayaran = DB::transaction(fn () => Pembayaran::create($this->lengkapiData($request->validated())));

        return $this->kembali($pembayaran)->with('success', 'Pembayaran berhasil dicatat. Cashflow & status nota diperbarui.');
    }

    public function edit(Pembayaran $pembayaran): View
    {
        return view('pembayaran.form', $this->dataForm($pembayaran));
    }

    public function update(PembayaranRequest $request, Pembayaran $pembayaran): RedirectResponse
    {
        DB::transaction(fn () => $pembayaran->update($this->lengkapiData($request->validated())));

        return $this->kembali($pembayaran)->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function destroy(Pembayaran $pembayaran, RiwayatService $riwayat): RedirectResponse
    {
        $riwayat->hapus($pembayaran, fn () => $pembayaran->delete());

        return back()->with('success', 'Pembayaran berhasil dihapus. Cashflow & status nota diperbarui.');
    }

    /**
     * JSON pilihan nota klien untuk "Kaitkan ke Nota": nota yang belum lunas (+ nota yang sedang dipilih).
     * GET pembayaran/nota?klien_id=3&selected=12&pembayaran=ID
     */
    public function notaKlien(Request $request): JsonResponse
    {
        $pilih = $request->integer('selected');
        $pembayaran = Pembayaran::find($request->integer('pembayaran'));

        $notas = Transaksi::query()
            ->where('klien_id', $request->integer('klien_id'))
            ->where(fn ($q) => $q->where('status_bayar', '!=', 'lunas')->when($pilih, fn ($x) => $x->orWhere('id', $pilih)))
            ->withSum('pembayarans', 'jumlah')
            ->latest('tanggal')->latest('id')
            ->limit(200)
            ->get(['id', 'tanggal', 'total_jual']);

        return response()->json($notas->map(function ($t) use ($pembayaran) {
            // Saat edit, pembayaran ini sendiri tidak dihitung sebagai "sudah dibayar"
            $sisa = $t->sisaPiutang() + ($pembayaran && $pembayaran->transaksi_id == $t->id ? (float) $pembayaran->jumlah : 0);

            return [
                'value' => (string) $t->id,
                'text' => 'Nota ' . tanggal($t->tanggal) . ' · total ' . rupiah($t->total_jual) . ' · sisa ' . rupiah($sisa),
                'total' => (float) $t->total_jual,
                'sisa' => round($sisa, 2),
            ];
        })->values());
    }

    /** supplier_id diambil dari faktur pembelian; kolom yang tidak relevan dikosongkan */
    private function lengkapiData(array $data): array
    {
        if ($data['jenis'] === 'supplier') {
            $data['supplier_id'] = PembelianStok::lockForUpdate()->findOrFail($data['pembelian_stok_id'])->supplier_id;
            $data['klien_id'] = null;
            $data['transaksi_id'] = null;
        } else {
            $data['supplier_id'] = null;
            $data['pembelian_stok_id'] = null;
            if (! empty($data['transaksi_id'])) {
                Transaksi::lockForUpdate()->findOrFail($data['transaksi_id']);
            }
        }

        return $data;
    }

    private function kembali(Pembayaran $pembayaran): RedirectResponse
    {
        return $pembayaran->jenis === 'supplier'
            ? redirect()->route('pembelian-stok.show', $pembayaran->pembelian_stok_id)
            : redirect()->route('pembayaran.index');
    }

    private function dataForm(Pembayaran $pembayaran): array
    {
        $pembelian = $pembayaran->jenis === 'supplier' && $pembayaran->pembelian_stok_id
            ? PembelianStok::with('supplier')->withSum('pembayarans', 'jumlah')->find($pembayaran->pembelian_stok_id)
            : null;
        $sisaHutang = $pembelian
            ? $pembelian->sisaHutang() + ($pembayaran->exists ? (float) $pembayaran->jumlah : 0)
            : 0;

        return [
            'pembayaran' => $pembayaran,
            'kliens' => Klien::orderBy('nama')->get(['id', 'nama', 'kategori']),
            'pembelian' => $pembelian,
            'sisaHutang' => $sisaHutang,
        ];
    }
}
