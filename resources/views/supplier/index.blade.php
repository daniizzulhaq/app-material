@extends('layouts.admin')

@section('title', 'Supplier')
@section('page-title', 'Supplier')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form method="GET" class="d-flex gap-2" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari nama, no. HP, alamat..." style="min-width: 240px">
            <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('supplier.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Supplier</a>
    </div>

    @if ($suppliers->isEmpty())
        <x-empty-state icon="bi-truck" title="{{ request('q') ? 'Supplier tidak ditemukan' : 'Belum ada supplier' }}"
                       message="{{ request('q') ? 'Coba kata kunci lain.' : 'Tambahkan supplier tempat Anda mengambil barang.' }}">
            @unless (request('q'))
                <a href="{{ route('supplier.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah Supplier</a>
            @endunless
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nama</th>
                        <th>No. HP</th>
                        <th>Alamat</th>
                        <th>Keterangan</th>
                        <th class="text-end" style="width:110px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suppliers as $supplier)
                        <tr>
                            <td class="text-muted">{{ $suppliers->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $supplier->nama }}</td>
                            <td>{{ $supplier->no_hp ?: '-' }}</td>
                            <td class="text-muted">{{ Str::limit($supplier->alamat, 50) ?: '-' }}</td>
                            <td class="text-muted">{{ Str::limit($supplier->keterangan, 40) ?: '-' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('supplier.edit', $supplier) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-tombol-hapus :action="route('supplier.destroy', $supplier)" :nama="$supplier->nama" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">Menampilkan {{ $suppliers->firstItem() }}–{{ $suppliers->lastItem() }} dari {{ $suppliers->total() }} supplier</small>
            {{ $suppliers->links() }}
        </div>
    @endif
</div>
@endsection
