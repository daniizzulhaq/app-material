<?php

namespace App\Http\Controllers;

use App\Exports\LaporanExport;
use App\Models\Barang;
use App\Models\Klien;
use App\Models\Supplier;
use App\Services\LaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/** Semua laporan mendukung ?export=pdf dan ?export=excel */
class LaporanController extends Controller
{
    public function __construct(private LaporanService $laporan)
    {
    }

    public function klien(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $data = $this->laporan->klien($dari, $sampai, $request->integer('klien_id') ?: null);

        return $this->tampilkan($request, 'klien', 'Laporan Per Klien', $data + [
            'dari' => $dari, 'sampai' => $sampai,
            'kliens' => Klien::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function supplier(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $data = $this->laporan->supplier($dari, $sampai, $request->integer('supplier_id') ?: null);

        return $this->tampilkan($request, 'supplier', 'Laporan Per Supplier', $data + [
            'dari' => $dari, 'sampai' => $sampai,
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function barang(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $data = $this->laporan->barang($dari, $sampai, $request->integer('barang_id') ?: null);

        return $this->tampilkan($request, 'barang', 'Laporan Per Barang', $data + [
            'dari' => $dari, 'sampai' => $sampai,
            'barangs' => Barang::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function stok(Request $request): Response|View
    {
        [$dari, $sampai] = $this->periode($request);
        $data = $this->laporan->stok($dari, $sampai);

        return $this->tampilkan($request, 'stok', 'Laporan Stok', $data + ['dari' => $dari, 'sampai' => $sampai]);
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

    private function tampilkan(Request $request, string $jenis, string $judul, array $data): Response|View
    {
        $tabel = "laporan.tabel.{$jenis}";
        $namaFile = Str::slug($judul) . '-' . $data['dari'] . '-sd-' . $data['sampai'];

        return match ($request->input('export')) {
            'pdf' => Pdf::loadView('laporan.pdf', $data + ['tabel' => $tabel, 'judul' => $judul, 'excel' => false])
                ->setPaper('a4', $jenis === 'stok' ? 'landscape' : 'portrait')
                ->download("{$namaFile}.pdf"),
            'excel' => Excel::download(new LaporanExport($tabel, $data, $judul), "{$namaFile}.xlsx"),
            default => view('laporan.halaman', $data + [
                'tabel' => $tabel,
                'judul' => $judul,
                'excel' => false,
                'filterTambahan' => $jenis === 'stok' ? null : "laporan.filter.{$jenis}",
            ]),
        };
    }
}
