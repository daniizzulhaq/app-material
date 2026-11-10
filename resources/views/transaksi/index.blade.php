@extends('layouts.admin')

@section('title', 'Rekapan Nota')
@section('page-title', 'Rekapan (Nota Penjualan)')

@section('content')
<x-filter-periode>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Kategori Klien</label>
        <select name="kategori" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach (\App\Models\Klien::KATEGORI as $val => $k)
                <option value="{{ $val }}" @selected(request('kategori') === $val)>{{ $k['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small text-muted mb-1" for="f-klien">Klien <span class="fw-normal">· bisa pilih beberapa</span></label>
        <select id="f-klien" name="klien_id[]" multiple class="tom-select" placeholder="Semua klien">
            @foreach ($kliens as $k)
                <option value="{{ $k->id }}" @selected(in_array($k->id, $klienIds))>{{ $k->nama }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small text-muted mb-1" for="f-supplier">Supplier <span class="fw-normal">· bisa pilih beberapa</span></label>
        <select id="f-supplier" name="supplier_id[]" multiple class="tom-select" placeholder="Semua supplier">
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected(in_array($s->id, $supplierIds))>{{ $s->nama }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Barang</label>
        <select name="barang_id" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach ($barangs as $b)
                <option value="{{ $b->id }}" @selected(request('barang_id') == $b->id)>{{ $b->nama }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Sumber</label>
        <select name="sumber" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="supplier" @selected(request('sumber') === 'supplier')>Supplier</option>
            <option value="stok" @selected(request('sumber') === 'stok')>Stok</option>
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
</x-filter-periode>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card label="Jumlah Nota" :value="number_format($ringkasan->jumlah, 0, ',', '.')" icon="bi-receipt" color="blue" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total Jual" :value="rupiah($ringkasan->jual)" icon="bi-cash-coin" color="green" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total Modal" :value="rupiah($ringkasan->modal)" icon="bi-wallet2" color="yellow" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Laba" :value="rupiah($ringkasan->laba)" icon="bi-graph-up-arrow" color="purple" /></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form method="GET" class="d-flex gap-2" role="search">
            @foreach (request()->except(['q', 'page']) as $k => $v)
                @foreach ((array) $v as $item)
                    @if (is_scalar($item))
                        <input type="hidden" name="{{ is_array($v) ? $k . '[]' : $k }}" value="{{ $item }}">
                    @endif
                @endforeach
            @endforeach
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari no. nota...">
            <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('transaksi.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nota Baru</a>
    </div>

    @if ($transaksis->isEmpty())
        <x-empty-state icon="bi-receipt" title="Belum ada nota"
                       message="Buat nota penjualan pertama Anda. Item bisa diambil dari supplier atau stok gudang.">
            <a href="{{ route('transaksi.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nota Baru</a>
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No. Nota</th>
                        <th>Tanggal</th>
                        <th>Klien</th>
                        <th class="text-end">Item</th>
                        <th class="text-end">Total Jual</th>
                        <th class="text-end">Modal</th>
                        <th class="text-end">Laba</th>
                        <th class="text-end">Sisa</th>
                        <th>Status</th>
                        <th class="text-end" style="width:170px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transaksis as $t)
                        <tr>
                            <td><a href="{{ route('transaksi.show', $t) }}" class="fw-semibold text-decoration-none">{{ $t->no_nota }}</a></td>
                            <td class="text-nowrap">{{ tanggal($t->tanggal) }}</td>
                            <td>{{ $t->klien->nama }} <x-kategori-badge :kategori="$t->klien->kategori" class="ms-1" /></td>
                            <td class="num">{{ $t->details_count }}</td>
                            <td class="num fw-semibold">{{ rupiah($t->total_jual) }}</td>
                            <td class="num text-muted">{{ rupiah($t->total_modal) }}</td>
                            <td class="num {{ $t->laba < 0 ? 'text-danger' : 'text-success' }}">{{ rupiah($t->laba) }}</td>
                            <td class="num">{{ $t->sisaPiutang() > 0 ? rupiah($t->sisaPiutang()) : '-' }}</td>
                            <td><x-status-badge :status="$t->status_bayar" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('transaksi.show', $t) }}" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('transaksi.edit', $t) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="{{ route('transaksi.cetak', $t) }}" target="_blank" class="btn btn-sm btn-light" title="Cetak nota"><i class="bi bi-printer"></i></a>
                                <x-tombol-hapus :action="route('transaksi.destroy', $t)" :nama="$t->no_nota" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">{{ $transaksis->total() }} nota</small>
            {{ $transaksis->links() }}
        </div>
    @endif
</div>
@endsection
