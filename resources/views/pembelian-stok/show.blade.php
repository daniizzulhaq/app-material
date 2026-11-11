@extends('layouts.admin')

@section('title', $pembelian->no_faktur)
@section('page-title', "Pembelian {$pembelian->no_faktur}")
@section('breadcrumb')
    <a href="{{ route('pembelian-stok.index') }}">Pembelian Stok</a> / Detail
@endsection

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
    @if ($pembelian->metode_bayar === 'hutang' && $pembelian->status_bayar !== 'lunas')
        <a href="{{ route('pembayaran.create', ['pembelian_stok_id' => $pembelian->id]) }}" class="btn btn-sm btn-success">
            <i class="bi bi-credit-card me-1"></i>Bayar Hutang
        </a>
    @endif
    <a href="{{ route('pembelian-stok.edit', $pembelian) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1"></i>Edit</a>
    <x-tombol-hapus :action="route('pembelian-stok.destroy', $pembelian)" :nama="$pembelian->no_faktur" label="Hapus" />
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6 class="card-title">Daftar Barang</h6></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Harga Beli</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pembelian->details as $d)
                            <tr>
                                <td>
                                    <a href="{{ route('stok.show', $d->barang) }}" class="fw-semibold text-decoration-none">{{ $d->barang->nama }}</a>
                                </td>
                                <td class="num">{{ qty($d->qty) }} {{ $d->barang->satuan }}</td>
                                <td class="num">{{ rupiah($d->harga_beli) }}</td>
                                <td class="num fw-semibold">{{ rupiah($d->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">Total</th><th class="num fs-6">{{ rupiah($pembelian->total) }}</th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">No. Faktur</dt><dd class="col-7 fw-semibold">{{ $pembelian->no_faktur }}</dd>
                    <dt class="col-5 text-muted fw-normal">Tanggal</dt><dd class="col-7">{{ tanggal($pembelian->tanggal) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Supplier</dt><dd class="col-7">{{ $pembelian->supplier->nama }}</dd>
                    <dt class="col-5 text-muted fw-normal">Metode</dt><dd class="col-7">{{ ucfirst($pembelian->metode_bayar) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Status</dt><dd class="col-7"><x-status-badge :status="$pembelian->status_bayar" /></dd>
                    <dt class="col-5 text-muted fw-normal">Dibayar</dt><dd class="col-7">{{ rupiah($pembelian->totalDibayar()) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Sisa Hutang</dt><dd class="col-7 fw-semibold text-danger">{{ rupiah($pembelian->sisaHutang()) }}</dd>
                    @if ($pembelian->keterangan)
                        <dt class="col-5 text-muted fw-normal">Keterangan</dt><dd class="col-7">{{ $pembelian->keterangan }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        @if ($pembelian->metode_bayar === 'hutang')
            <div class="card">
                <div class="card-header"><h6 class="card-title">Riwayat Pembayaran</h6></div>
                @forelse ($pembelian->pembayarans as $bayar)
                    <div class="card-body py-2 border-bottom d-flex justify-content-between align-items-center gap-2 small">
                        <span>{{ tanggal($bayar->tanggal) }} · {{ ucfirst($bayar->metode) }}</span>
                        <span class="text-nowrap">
                            <strong class="me-2">{{ rupiah($bayar->jumlah) }}</strong>
                            <a href="{{ route('pembayaran.edit', $bayar) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                            <x-tombol-hapus :action="route('pembayaran.destroy', $bayar)" :nama="'pembayaran ' . rupiah($bayar->jumlah)" />
                        </span>
                    </div>
                @empty
                    <div class="card-body small text-muted">Belum ada pembayaran.</div>
                @endforelse
            </div>
        @endif
    </div>
</div>
@endsection
