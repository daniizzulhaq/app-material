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
            {{-- Lihat PDF di tab baru (tidak tersimpan otomatis); simpan file lewat "Unduh PDF" --}}
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'unduh' => null]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger"><i class="bi bi-eye me-1"></i>Lihat PDF</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'unduh' => 1]) }}" class="btn btn-sm btn-outline-danger" title="Simpan file PDF"><i class="bi bi-download me-1"></i>Unduh PDF</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'unduh' => null]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        </div>
    </x-slot:aksi>
</x-filter-periode>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title">{{ $judul }}</h6>
        <small class="text-muted text-end">Periode {{ tanggal($dari) }} – {{ tanggal($sampai) }}@if (! empty($subjek))<br>{{ $subjek }}@endif</small>
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
