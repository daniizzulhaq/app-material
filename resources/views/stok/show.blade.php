@extends('layouts.admin')

@section('title', "Kartu Stok {$barang->nama}")
@section('page-title', 'Kartu Stok')
@section('breadcrumb')
    <a href="{{ route('stok.index') }}">Stok Barang</a> / {{ $barang->nama }}
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-4 align-items-center">
        <div class="me-auto">
            <h5 class="fw-bold mb-0">{{ $barang->nama }}</h5>
        </div>
        <div><small class="text-muted d-block">Stok saat ini</small><strong class="fs-5">{{ qty($barang->stok) }}</strong> {{ $barang->satuan }}
            @if ($barang->menipis) <span class="badge badge-menipis ms-1">Menipis</span> @endif
        </div>
        <div><small class="text-muted d-block">HPP rata-rata</small><strong class="fs-5">{{ rupiah($barang->hpp) }}</strong></div>
        <div><small class="text-muted d-block">Nilai stok</small><strong class="fs-5">{{ rupiah($barang->nilai_stok) }}</strong></div>
    </div>
</div>

<x-filter-periode :dari="$dari" :sampai="$sampai" />

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Referensi</th>
                    <th>Keterangan</th>
                    <th class="text-end">Harga</th>
                    <th class="text-end">Masuk</th>
                    <th class="text-end">Keluar</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light">
                    <td colspan="6" class="fw-semibold">Saldo awal per {{ tanggal($dari) }}</td>
                    <td class="num fw-bold">{{ qty($saldoAwal) }}</td>
                </tr>
                @php
                    $masuk = 0;
                    $keluar = 0;
                @endphp
                @forelse ($mutasis as $m)
                    @php
                        $q = $m->qty_bertanda;
                        if ($q >= 0) {
                            $masuk += $q;
                        } else {
                            $keluar += abs($q);
                        }
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ tanggal($m->tanggal) }}</td>
                        <td>
                            @if ($m->tipe === 'penyesuaian')
                                <span class="badge badge-sebagian">Penyesuaian</span>
                            @elseif ($m->tipe === 'masuk')
                                <span class="badge badge-lunas">Masuk</span>
                            @else
                                <span class="badge badge-belum">Keluar</span>
                            @endif
                            @if ($m->referensi_url)
                                <a href="{{ $m->referensi_url }}" class="small text-decoration-none ms-1">{{ $m->referensi_label }}</a>
                            @else
                                <span class="small text-muted ms-1">{{ $m->referensi_label }}</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $m->keterangan }}</td>
                        <td class="num">{{ rupiah($m->harga) }}</td>
                        <td class="num text-success">{{ $q > 0 ? qty($q) : '' }}</td>
                        <td class="num text-danger">{{ $q < 0 ? qty(abs($q)) : '' }}</td>
                        <td class="num fw-semibold">{{ qty($m->stok_sesudah) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-empty-state icon="bi-journal" title="Tidak ada mutasi" message="Belum ada pergerakan stok pada periode ini." class="py-4" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($mutasis->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Total periode</th>
                        <th class="num text-success">{{ qty($masuk) }}</th>
                        <th class="num text-danger">{{ qty($keluar) }}</th>
                        <th class="num">{{ qty($mutasis->last()->stok_sesudah) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
