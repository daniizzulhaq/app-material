@extends('layouts.admin')

@section('title', $transaksi->label())
@section('page-title', $transaksi->label())
@section('breadcrumb')
    <a href="{{ route('transaksi.index') }}">Rekapan</a> / Detail
@endsection

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
    @if ($transaksi->status_bayar !== 'lunas')
        <a href="{{ route('pembayaran.create', ['transaksi_id' => $transaksi->id]) }}" class="btn btn-sm btn-success">
            <i class="bi bi-cash-coin me-1"></i>Terima Pembayaran
        </a>
    @endif
    <a href="{{ route('transaksi.cetak', $transaksi) }}" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>Cetak</a>
    <a href="{{ route('transaksi.cetak', [$transaksi, 'pdf' => 1]) }}" class="btn btn-sm btn-light"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <a href="{{ route('transaksi.edit', $transaksi) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1"></i>Edit</a>
    <x-tombol-hapus :action="route('transaksi.destroy', $transaksi)" :nama="$transaksi->label()" label="Hapus" />
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card label="Total Jual" :value="rupiah($transaksi->total_jual)" icon="bi-cash-coin" color="green" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total Modal" :value="rupiah($transaksi->total_modal)" icon="bi-wallet2" color="yellow" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Laba" :value="rupiah($transaksi->laba)" icon="bi-graph-up-arrow" :color="$transaksi->laba < 0 ? 'red' : 'purple'" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Belanja Supplier" :value="rupiah($transaksi->total_belanja_supplier)" icon="bi-truck" color="blue" /></div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h6 class="card-title">Item Nota</h6></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Sumber</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Harga Beli</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-end">Subtotal Jual</th>
                            <th class="text-end">Laba</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transaksi->details as $d)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $d->barang->nama }}</span>
                                </td>
                                <td>
                                    <x-sumber-badge :sumber="$d->sumber" />
                                    <div class="small text-muted mt-1">{{ $d->supplier?->nama ?? 'Gudang sendiri' }}</div>
                                </td>
                                <td class="num">{{ qty($d->qty) }} {{ $d->barang->satuan }}</td>
                                <td class="num">{{ rupiah($d->harga_beli) }}</td>
                                <td class="num">{{ rupiah($d->harga_jual) }}</td>
                                <td class="num fw-semibold">{{ rupiah($d->subtotal_jual) }}</td>
                                <td class="num {{ $d->subtotal_jual - $d->subtotal_beli < 0 ? 'text-danger' : 'text-success' }}">{{ rupiah($d->subtotal_jual - $d->subtotal_beli) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-end">Total</th>
                            <th class="num">{{ qty($transaksi->total_qty) }}</th>
                            <th class="num">{{ rupiah($transaksi->total_modal) }}</th>
                            <th></th>
                            <th class="num">{{ rupiah($transaksi->total_jual) }}</th>
                            <th class="num">{{ rupiah($transaksi->laba) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Tanggal</dt><dd class="col-7">{{ tanggal($transaksi->tanggal) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Klien</dt><dd class="col-7">{{ $transaksi->klien->nama }}</dd>
                    <dt class="col-5 text-muted fw-normal">Status</dt><dd class="col-7"><x-status-badge :status="$transaksi->status_bayar" /></dd>
                    <dt class="col-5 text-muted fw-normal">Dibayar</dt><dd class="col-7">{{ rupiah($transaksi->totalDibayar()) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Sisa Piutang</dt><dd class="col-7 fw-semibold text-danger">{{ rupiah($transaksi->sisaPiutang()) }}</dd>
                    @if ($transaksi->keterangan)
                        <dt class="col-5 text-muted fw-normal">Keterangan</dt><dd class="col-7">{{ $transaksi->keterangan }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="card-title">Riwayat Pembayaran</h6></div>
            @forelse ($transaksi->pembayarans as $bayar)
                <div class="card-body py-2 border-bottom d-flex justify-content-between small">
                    <span>{{ tanggal($bayar->tanggal) }} · {{ ucfirst($bayar->metode) }}</span>
                    <strong>{{ rupiah($bayar->jumlah) }}</strong>
                </div>
            @empty
                <div class="card-body small text-muted">Belum ada pembayaran.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
