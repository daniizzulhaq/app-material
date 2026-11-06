@extends('layouts.admin')

@section('title', 'Klien')
@section('page-title', 'Klien')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form method="GET" class="d-flex gap-2" role="search">
            <input type="hidden" name="kategori" value="{{ request('kategori') }}">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari nama, no. HP, alamat..." style="min-width: 240px">
            <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('klien.create', ['kategori' => request('kategori')]) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Klien</a>
    </div>
    <div class="card-body py-2 border-bottom">
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Kategori klien">
            <a href="{{ request()->fullUrlWithQuery(['kategori' => null, 'page' => null]) }}"
               class="btn {{ request('kategori') ? 'btn-light' : 'btn-primary' }}">Semua ({{ $jumlahPerKategori->sum() }})</a>
            @foreach (\App\Models\Klien::KATEGORI as $val => $k)
                <a href="{{ request()->fullUrlWithQuery(['kategori' => $val, 'page' => null]) }}"
                   class="btn {{ request('kategori') === $val ? 'btn-primary' : 'btn-light' }}"><i class="bi {{ $k['icon'] }} me-1"></i>{{ $k['label'] }} ({{ $jumlahPerKategori[$val] ?? 0 }})</a>
            @endforeach
        </div>
    </div>

    @if ($kliens->isEmpty())
        <x-empty-state icon="bi-people" title="{{ request('q') ? 'Klien tidak ditemukan' : 'Belum ada klien' }}"
                       message="{{ request('q') ? 'Coba kata kunci lain.' : 'Tambahkan klien pelanggan yang membeli barang.' }}">
            @unless (request('q'))
                <a href="{{ route('klien.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah Klien</a>
            @endunless
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle" data-mobile-judul="2">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>No. HP</th>
                        <th>Alamat</th>
                        <th>Keterangan</th>
                        <th class="text-end">Jumlah Nota</th>
                        <th class="text-end" style="width:110px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kliens as $klien)
                        <tr>
                            <td class="text-muted">{{ $kliens->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $klien->nama }}</td>
                            <td><x-kategori-badge :kategori="$klien->kategori" /></td>
                            <td>{{ $klien->no_hp ?: '-' }}</td>
                            <td class="text-muted">{{ Str::limit($klien->alamat, 50) ?: '-' }}</td>
                            <td class="text-muted">{{ Str::limit($klien->keterangan, 40) ?: '-' }}</td>
                            <td class="num">
                                <a href="{{ route('transaksi.index', ['klien_id' => $klien->id]) }}" class="text-decoration-none">{{ $klien->transaksis_count }}</a>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('klien.edit', $klien) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-tombol-hapus :action="route('klien.destroy', $klien)" :nama="$klien->nama" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">Menampilkan {{ $kliens->firstItem() }}–{{ $kliens->lastItem() }} dari {{ $kliens->total() }} klien</small>
            {{ $kliens->links() }}
        </div>
    @endif
</div>
@endsection
