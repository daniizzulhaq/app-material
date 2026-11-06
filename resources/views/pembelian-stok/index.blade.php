@extends('layouts.admin')

@section('title', 'Pembelian Stok')
@section('page-title', 'Pembelian Stok')

@section('content')
<x-filter-periode>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Supplier</label>
        <select name="supplier_id" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->nama }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Metode</label>
        <select name="metode_bayar" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="tunai" @selected(request('metode_bayar') === 'tunai')>Tunai</option>
            <option value="hutang" @selected(request('metode_bayar') === 'hutang')>Hutang</option>
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Status</label>
        <select name="status_bayar" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="belum" @selected(request('status_bayar') === 'belum')>Belum</option>
            <option value="sebagian" @selected(request('status_bayar') === 'sebagian')>Sebagian</option>
            <option value="lunas" @selected(request('status_bayar') === 'lunas')>Lunas</option>
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">No. Faktur</label>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="PB-...">
    </div>
</x-filter-periode>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div>
            <span class="text-muted small">Total sesuai filter:</span>
            <strong class="ms-1">{{ rupiah($totalFilter) }}</strong>
        </div>
        <a href="{{ route('pembelian-stok.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Pembelian Baru</a>
    </div>

    @if ($pembelians->isEmpty())
        <x-empty-state icon="bi-cart-plus" title="Belum ada pembelian stok"
                       message="Catat pembelian barang untuk stok gudang. Stok dan HPP akan diperbarui otomatis.">
            <a href="{{ route('pembelian-stok.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Pembelian Baru</a>
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No. Faktur</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th class="text-end">Item</th>
                        <th class="text-end">Total</th>
                        <th>Metode</th>
                        <th class="text-end">Sisa Hutang</th>
                        <th>Status</th>
                        <th class="text-end" style="width:130px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pembelians as $p)
                        <tr>
                            <td><a href="{{ route('pembelian-stok.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->no_faktur }}</a></td>
                            <td class="text-nowrap">{{ tanggal($p->tanggal) }}</td>
                            <td>{{ $p->supplier->nama }}</td>
                            <td class="num">{{ $p->details_count }}</td>
                            <td class="num fw-semibold">{{ rupiah($p->total) }}</td>
                            <td><span class="badge {{ $p->metode_bayar === 'tunai' ? 'bg-light text-dark' : 'badge-sebagian' }}">{{ ucfirst($p->metode_bayar) }}</span></td>
                            <td class="num">{{ $p->sisaHutang() > 0 ? rupiah($p->sisaHutang()) : '-' }}</td>
                            <td><x-status-badge :status="$p->status_bayar" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('pembelian-stok.show', $p) }}" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('pembelian-stok.edit', $p) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-tombol-hapus :action="route('pembelian-stok.destroy', $p)" :nama="$p->no_faktur" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">{{ $pembelians->total() }} faktur</small>
            {{ $pembelians->links() }}
        </div>
    @endif
</div>
@endsection
