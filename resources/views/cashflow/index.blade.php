@extends('layouts.admin')

@section('title', 'Cashflow')
@section('page-title', 'Cashflow')

@section('content')
<x-filter-periode :dari="$dari" :sampai="$sampai">
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Kategori</label>
        <select name="kategori" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach (\App\Models\Cashflow::KATEGORI as $val => $label)
                <option value="{{ $val }}" @selected(request('kategori') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Tipe</label>
        <select name="tipe" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="masuk" @selected(request('tipe') === 'masuk')>Masuk</option>
            <option value="keluar" @selected(request('tipe') === 'keluar')>Keluar</option>
        </select>
    </div>
</x-filter-periode>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card label="Saldo Awal" :value="rupiah($saldoAwal)" icon="bi-wallet" color="blue" :hint="'per ' . tanggal($dari)" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total Masuk" :value="rupiah($totalPeriode->masuk)" icon="bi-arrow-down-circle" color="green" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total Keluar" :value="rupiah($totalPeriode->keluar)" icon="bi-arrow-up-circle" color="red" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Saldo Akhir" :value="rupiah($saldoAkhir)" icon="bi-safe2" color="purple" :hint="'per ' . tanggal($sampai)" /></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="small">
            @if (request()->hasAny(['kategori', 'tipe']) && (request('kategori') || request('tipe')))
                <span class="text-muted">Sesuai filter:</span>
                <span class="text-success ms-1">+{{ rupiah($totalFilter->masuk) }}</span>
                <span class="text-danger ms-1">−{{ rupiah($totalFilter->keluar) }}</span>
            @else
                <span class="text-muted"><i class="bi bi-info-circle me-1"></i>Baris berlabel <span class="badge badge-otomatis">Otomatis</span> dibuat sistem dari pembelian, nota, dan pembayaran.</span>
            @endif
        </div>
        <a href="{{ route('cashflow.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Input Manual</a>
    </div>

    @if ($cashflows->isEmpty())
        <x-empty-state icon="bi-cash-stack" title="Belum ada arus kas pada periode ini"
                       message="Cashflow terisi otomatis dari transaksi, atau tambahkan manual (modal, operasional)." />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-end">Masuk</th>
                        <th class="text-end">Keluar</th>
                        <th class="text-end" style="width:110px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cashflows as $c)
                        <tr>
                            <td class="text-nowrap">{{ tanggal($c->tanggal) }}</td>
                            <td>
                                {{ $c->kategori_label }}
                                @if ($c->otomatis) <span class="badge badge-otomatis ms-1"><i class="bi bi-robot me-1"></i>Otomatis</span> @endif
                            </td>
                            <td class="small text-muted">{{ $c->keterangan }}</td>
                            <td class="num text-success">{{ $c->tipe === 'masuk' ? rupiah($c->nominal) : '' }}</td>
                            <td class="num text-danger">{{ $c->tipe === 'keluar' ? rupiah($c->nominal) : '' }}</td>
                            <td class="text-end text-nowrap">
                                @if ($c->otomatis)
                                    <span class="text-muted small" title="Ubah dari dokumen sumber"><i class="bi bi-lock"></i></span>
                                @else
                                    <a href="{{ route('cashflow.edit', $c) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <x-tombol-hapus :action="route('cashflow.destroy', $c)" :nama="$c->keterangan" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-end">{{ $cashflows->links() }}</div>
    @endif
</div>
@endsection
