<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export Excel generik: memakai partial tabel laporan yang sama dengan halaman/PDF,
 * dengan $excel = true agar angka tetap berupa angka (bisa dijumlah di Excel).
 */
class LaporanExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        private string $view,
        private array $data,
        private string $judul,
    ) {
    }

    public function view(): View
    {
        return view('laporan.excel', $this->data + ['tabel' => $this->view, 'judul' => $this->judul, 'excel' => true]);
    }

    public function title(): string
    {
        return mb_substr($this->judul, 0, 31);
    }
}
