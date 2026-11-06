{{-- Kerangka halaman laporan. Variabel: $judul, $tabel, $dari, $sampai, $rows, $filterTambahan --}}
@extends('layouts.admin')

@section('title', $judul)
@section('page-title', $judul)

@section('content')
<x-filter-periode :dari="$dari" :sampai="$sampai">
    @if ($filterTambahan)
        @include($filterTambahan)
    @endif

    <x-slot:aksi>
        <div class="btn-group">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        </div>
    </x-slot:aksi>
</x-filter-periode>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title">{{ $judul }}</h6>
        <small class="text-muted">Periode {{ tanggal($dari) }} – {{ tanggal($sampai) }}</small>
    </div>
    @if ($rows->isEmpty())
        <x-empty-state icon="bi-bar-chart" title="Tidak ada data" message="Belum ada transaksi pada periode ini. Coba ubah rentang tanggal." />
    @else
        <div class="table-responsive">
            @include($tabel)
        </div>
    @endif
</div>
@endsection
