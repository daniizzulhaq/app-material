@extends('layouts.admin')

@section('title', 'Pembayaran')
@section('page-title', 'Pembayaran Klien')

@section('content')
<x-filter-periode>
    <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small text-muted mb-1" for="f-klien">Klien <span class="fw-normal">· bisa pilih beberapa</span></label>
        <select id="f-klien" name="klien_id[]" multiple class="tom-select" placeholder="Semua klien">
            @foreach ($kliens as $k)
                <option value="{{ $k->id }}" @selected(in_array($k->id, $klienIds))>{{ $k->nama }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Metode</label>
        <select name="metode" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="tunai" @selected(request('metode') === 'tunai')>Tunai</option>
            <option value="transfer" @selected(request('metode') === 'transfer')>Transfer</option>
        </select>
    </div>
</x-filter-periode>

<div class="row g-3 mb-3">
    <div class="col-sm-6"><x-stat-card label="Uang Masuk dari Klien" :value="rupiah($ringkasan->masuk)" icon="bi-arrow-down-circle" color="green" /></div>
    <div class="col-sm-6"><x-stat-card label="Jumlah Pembayaran" :value="number_format($ringkasan->jumlah, 0, ',', '.')" icon="bi-receipt" color="blue" /></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Belanja ke supplier dari nota sudah lunas otomatis per hari (lihat Cashflow).</span>
        <a href="{{ route('pembayaran.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-arrow-down-circle me-1"></i>Terima dari Klien</a>
    </div>

    @if ($pembayarans->isEmpty())
        <x-empty-state icon="bi-credit-card" title="Belum ada pembayaran"
                       message="Catat uang masuk dari klien dan kaitkan ke nota yang dibayar." />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Klien</th>
                        <th>Nota</th>
                        <th>Metode</th>
                        <th class="text-end">Jumlah</th>
                        <th>Keterangan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pembayarans as $p)
                        <tr>
                            <td class="text-nowrap">{{ tanggal($p->tanggal) }}</td>
                            <td class="fw-semibold">{{ $p->klien?->nama }}</td>
                            <td>
                                @if ($p->transaksi)
                                    <a href="{{ route('transaksi.show', $p->transaksi) }}" class="text-decoration-none">{{ $p->dokumen }}</a>
                                    <x-status-badge :status="$p->transaksi->status_bayar" />
                                @else
                                    <span class="text-muted">Tanpa nota</span>
                                @endif
                            </td>
                            <td>{{ ucfirst($p->metode) }}</td>
                            <td class="num fw-semibold">{{ rupiah($p->jumlah) }}</td>
                            <td class="small text-muted">{{ Str::limit($p->keterangan, 40) ?: '-' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('pembayaran.edit', $p) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-tombol-hapus :action="route('pembayaran.destroy', $p)" :nama="'pembayaran ' . rupiah($p->jumlah)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-end">{{ $pembayarans->links() }}</div>
    @endif
</div>
@endsection
