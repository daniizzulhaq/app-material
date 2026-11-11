<?php

namespace App\Http\Controllers;

use App\Exports\LaporanExport;
use App\Models\Barang;
use App\Models\Klien;
use App\Models\Supplier;
use App\Services\LaporanService;
use App\Support\OpsiLaporan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Semua laporan mendukung:
 *  ?export=pdf          -> lihat PDF di browser (tidak tersimpan otomatis)
 *  ?export=pdf&unduh=1  -> simpan file PDF
 *  ?export=excel        -> unduh Excel
 * Klien & supplier bisa dipilih lebih dari satu (?klien_id[]=1&klien_id[]=2).
 * Opsi tampilan (seperti web sayur): ?qty=total|rincian, ?harga=lengkap|jual|beli|tanpa, ?klien=tampil|sembunyi.
 */
class LaporanController extends Controller
{
    public function __construct(private LaporanService $laporan)
    {
    }

    public function klien(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $klienIds = ids($request->input('klien_id'));
        $supplierIds = ids($request->input('supplier_id'));
        $opsi = OpsiLaporan::dariRequest($request, 'lengkap', true);
        $data = $this->laporan->klien($dari, $sampai, $klienIds, $request->input('kategori'), $supplierIds, $opsi);
        $judul = 'Laporan Per Klien' . ($data['kategori'] ? ' ' . Klien::KATEGORI[$data['kategori']]['label'] : '');

        return $this->tampilkan($request, 'klien', $judul, $data + [
            'dari' => $dari, 'sampai' => $sampai, 'opsi' => $opsi,
            'klienIds' => $klienIds,
            'supplierIds' => $supplierIds,
            'kliens' => Klien::kategori($data['kategori'])->orderBy('nama')->get(['id', 'nama', 'kategori']),
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'subjek' => $this->subjek([
                'Klien' => Klien::whereIn('id', $klienIds)->orderBy('nama')->pluck('nama'),
                'Supplier' => Supplier::whereIn('id', $supplierIds)->orderBy('nama')->pluck('nama'),
            ]),
        ]);
    }

    public function supplier(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $supplierIds = ids($request->input('supplier_id'));
        $klienIds = ids($request->input('klien_id'));
        $opsi = OpsiLaporan::dariRequest($request, 'beli', false);
        $data = $this->laporan->supplier($dari, $sampai, $supplierIds, $klienIds, $opsi);

        return $this->tampilkan($request, 'supplier', 'Laporan Per Supplier', $data + [
            'dari' => $dari, 'sampai' => $sampai, 'opsi' => $opsi,
            'supplierIds' => $supplierIds,
            'klienIds' => $klienIds,
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'kliens' => Klien::orderBy('nama')->get(['id', 'nama', 'kategori']),
            'subjek' => $this->subjek([
                'Supplier' => Supplier::whereIn('id', $supplierIds)->orderBy('nama')->pluck('nama'),
                'Klien' => Klien::whereIn('id', $klienIds)->orderBy('nama')->pluck('nama'),
            ]),
        ]);
    }

    public function barang(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $klienIds = ids($request->input('klien_id'));
        $supplierIds = ids($request->input('supplier_id'));
        $opsi = OpsiLaporan::dariRequest($request, 'lengkap', false);
        $data = $this->laporan->barang($dari, $sampai, $request->integer('barang_id') ?: null, $klienIds, $supplierIds, $opsi);

        return $this->tampilkan($request, 'barang', 'Laporan Per Barang', $data + [
            'dari' => $dari, 'sampai' => $sampai, 'opsi' => $opsi,
            'klienIds' => $klienIds,
            'supplierIds' => $supplierIds,
            'barangs' => Barang::orderBy('nama')->get(['id', 'nama']),
            'kliens' => Klien::orderBy('nama')->get(['id', 'nama', 'kategori']),
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'subjek' => $this->subjek([
                'Klien' => Klien::whereIn('id', $klienIds)->orderBy('nama')->pluck('nama'),
                'Supplier' => Supplier::whereIn('id', $supplierIds)->orderBy('nama')->pluck('nama'),
            ]),
        ]);
    }

    public function stok(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $supplierIds = ids($request->input('supplier_id'));
        $opsi = OpsiLaporan::dariRequest($request, 'lengkap', false);
        $data = $this->laporan->stok($dari, $sampai, $supplierIds);

        return $this->tampilkan($request, 'stok', 'Laporan Stok', $data + [
            'dari' => $dari, 'sampai' => $sampai, 'opsi' => $opsi,
            'supplierIds' => $supplierIds,
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'subjek' => $this->subjek(['Supplier' => Supplier::whereIn('id', $supplierIds)->orderBy('nama')->pluck('nama')]),
        ]);
    }

    /** Default periode: bulan ini */
    private function periode(Request $request): array
    {
        $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        return [
            $request->input('dari', now()->startOfMonth()->toDateString()),
            $request->input('sampai', now()->toDateString()),
        ];
    }

    /** Ringkasan pilihan filter untuk judul PDF, mis. "Klien: Toko A, Toko B" */
    private function subjek(array $daftar): ?string
    {
        return collect($daftar)
            ->filter(fn ($nama) => $nama->isNotEmpty())
            ->map(fn ($nama, $label) => "{$label}: " . $nama->implode(', '))
            ->implode(' · ') ?: null;
    }

    /** Tabel lebar (rincian barang dengan harga / laporan stok) dicetak mendatar */
    private function landscape(string $jenis, array $data): bool
    {
        $opsi = $data['opsi'];
        $adaRincian = isset($data['rincian']) && $data['rincian']->isNotEmpty();

        return match ($jenis) {
            'stok' => $opsi->adaHarga(),
            'barang' => $opsi->laba() || $opsi->tampilKlien,
            default => ($adaRincian && ($opsi->laba() || ($opsi->beli() && $opsi->jual()))) || ($jenis === 'supplier' && $opsi->laba()),
        };
    }

    private function tampilkan(Request $request, string $jenis, string $judul, array $data): Response|View
    {
        $tabel = "laporan.tabel.{$jenis}";
        $namaFile = Str::slug($judul) . '-' . $data['dari'] . '-sd-' . $data['sampai'];

        if ($request->input('export') === 'pdf') {
            $pdf = Pdf::loadView('laporan.pdf', $data + ['tabel' => $tabel, 'judul' => $judul, 'excel' => false, 'pdf' => true])
                ->setPaper('a4', $this->landscape($jenis, $data) ? 'landscape' : 'portrait');

            // Default: dibuka di browser untuk dilihat; simpan file hanya bila memilih "Unduh PDF"
            return $request->boolean('unduh') ? $pdf->download("{$namaFile}.pdf") : $pdf->stream("{$namaFile}.pdf");
        }

        return match ($request->input('export')) {
            'excel' => Excel::download(new LaporanExport($tabel, $data, $judul), "{$namaFile}.xlsx"),
            default => view('laporan.halaman', $data + [
                'tabel' => $tabel,
                'judul' => $judul,
                'excel' => false,
                'filterTambahan' => "laporan.filter.{$jenis}",
            ]),
        };
    }
}
