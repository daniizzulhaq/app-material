@extends('layouts.admin')

@section('title', 'Barang')
@section('page-title', 'Barang')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form method="GET" class="d-flex flex-wrap gap-2" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari nama barang..." style="min-width: 220px">
            <select name="satuan" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                <option value="">Semua satuan</option>
                @foreach ($satuans as $s)
                    <option value="{{ $s }}" @selected(request('satuan') === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <select name="supplier_id" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                <option value="">Semua supplier</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->nama }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('barang.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Barang</a>
    </div>

    @if ($barangs->isEmpty())
        <x-empty-state icon="bi-box-seam" title="{{ request()->hasAny(['q', 'satuan', 'supplier_id']) ? 'Barang tidak ditemukan' : 'Belum ada barang' }}"
                       message="Daftarkan barang yang Anda jual beserta supplier, satuan, harga beli & harga jual.">
            <a href="{{ route('barang.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah Barang</a>
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Supplier</th>
                        <th>Satuan</th>
                        <th class="text-end">Harga Beli</th>
                        <th class="text-end">Harga Jual</th>
                        <th class="text-end" style="width:140px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($barangs as $barang)
                        <tr>
                            <td class="fw-semibold">{{ $barang->nama }}</td>
                            <td>
                                @if ($barang->supplier)
                                    <i class="bi bi-truck text-muted me-1"></i>{{ $barang->supplier->nama }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $barang->satuan }}</td>
                            <td class="num">{{ rupiah($barang->harga_beli_default) }}</td>
                            <td class="num">{{ rupiah($barang->harga_jual_default) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('barang.edit', $barang) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-tombol-hapus :action="route('barang.destroy', $barang)" :nama="$barang->nama" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">Menampilkan {{ $barangs->firstItem() }}–{{ $barangs->lastItem() }} dari {{ $barangs->total() }} barang</small>
            {{ $barangs->links() }}
        </div>
    @endif
</div>
@endsection
