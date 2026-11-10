@extends('layouts.admin')

@section('title', 'Stok Barang')
@section('page-title', 'Stok Barang')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-4">
        <x-stat-card label="Jumlah Barang" :value="number_format($ringkasan->jumlah, 0, ',', '.')" icon="bi-box-seam" color="blue" />
    </div>
    <div class="col-sm-6 col-xl-4">
        <x-stat-card label="Nilai Stok (stok × HPP)" :value="rupiah($ringkasan->nilai)" icon="bi-safe" color="purple" />
    </div>
    <div class="col-sm-6 col-xl-4">
        <a href="{{ route('stok.index', ['menipis' => 1]) }}" class="text-decoration-none">
            <x-stat-card label="Stok Menipis" :value="$jumlahMenipis . ' barang'" icon="bi-exclamation-triangle" color="red" />
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari nama..." style="min-width: 220px">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="menipis" name="menipis" value="1" @checked(request()->boolean('menipis')) onchange="this.form.submit()">
                <label class="form-check-label small" for="menipis">Hanya stok menipis</label>
            </div>
            <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <div class="d-flex gap-2">
            <a href="{{ route('stok-opname.create') }}" class="btn btn-sm btn-light"><i class="bi bi-clipboard-check me-1"></i>Stok Opname</a>
            <a href="{{ route('pembelian-stok.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-cart-plus me-1"></i>Beli Stok</a>
        </div>
    </div>

    @if ($barangs->isEmpty())
        <x-empty-state icon="bi-boxes" title="{{ request()->boolean('menipis') ? 'Tidak ada stok menipis 🎉' : 'Belum ada barang' }}"
                       message="{{ request()->boolean('menipis') ? 'Semua stok barang masih di atas batas minimum.' : 'Barang masuk ke data stok setelah dibeli lewat Pembelian Stok.' }}">
            @unless (request()->boolean('menipis'))
                <a href="{{ route('pembelian-stok.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-cart-plus me-1"></i>Beli Stok</a>
            @endunless
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Satuan</th>
                        <th class="text-end">Stok</th>
                        <th class="text-end">HPP</th>
                        <th class="text-end">Nilai Stok</th>
                        <th class="text-end" style="width:110px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($barangs as $barang)
                        <tr>
                            <td class="fw-semibold">
                                {{ $barang->nama }}
                                @if ($barang->menipis)
                                    <span class="badge badge-menipis ms-1" title="Stok minimum {{ qty($barang->stok_minimum) }}">Menipis</span>
                                @endif
                            </td>
                            <td>{{ $barang->satuan }}</td>
                            <td class="num fw-semibold">{{ qty($barang->stok) }}</td>
                            <td class="num">{{ rupiah($barang->hpp) }}</td>
                            <td class="num">{{ rupiah($barang->nilai_stok) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('stok.show', $barang) }}" class="btn btn-sm btn-light" title="Kartu stok"><i class="bi bi-journal-text"></i></a>
                                <a href="{{ route('stok-opname.create', ['barang_id' => $barang->id]) }}" class="btn btn-sm btn-light" title="Opname"><i class="bi bi-clipboard-check"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-end">
            {{ $barangs->links() }}
        </div>
    @endif
</div>
@endsection
