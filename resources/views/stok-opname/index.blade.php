@extends('layouts.admin')

@section('title', 'Stok Opname')
@section('page-title', 'Stok Opname')

@section('content')
<x-filter-periode>
    <div class="col-12 col-md-4 col-xl-3">
        <label class="form-label small text-muted mb-1">Barang</label>
        <select name="barang_id" class="form-select form-select-sm">
            <option value="">Semua barang</option>
            @foreach ($barangs as $b)
                <option value="{{ $b->id }}" @selected(request('barang_id') == $b->id)>{{ $b->nama }}</option>
            @endforeach
        </select>
    </div>
</x-filter-periode>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div>
            <span class="text-muted small">Total nilai selisih:</span>
            <strong class="ms-1 {{ $totalNilai < 0 ? 'text-danger' : 'text-success' }}">{{ rupiah($totalNilai) }}</strong>
            <span class="small text-muted ms-1">(minus = kerugian)</span>
        </div>
        <a href="{{ route('stok-opname.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Opname Baru</a>
    </div>

    @if ($opnames->isEmpty())
        <x-empty-state icon="bi-clipboard-check" title="Belum ada stok opname"
                       message="Hitung stok fisik di gudang lalu catat di sini untuk menyesuaikan stok sistem.">
            <a href="{{ route('stok-opname.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Opname Baru</a>
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle" data-mobile-judul="2">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th class="text-end">Stok Sistem</th>
                        <th class="text-end">Stok Fisik</th>
                        <th class="text-end">Selisih</th>
                        <th class="text-end">Nilai Selisih</th>
                        <th>Keterangan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($opnames as $o)
                        <tr>
                            <td class="text-nowrap">{{ tanggal($o->tanggal) }}</td>
                            <td><a href="{{ route('stok.show', $o->barang) }}" class="fw-semibold text-decoration-none">{{ $o->barang->nama }}</a></td>
                            <td class="num">{{ qty($o->stok_sistem) }}</td>
                            <td class="num">{{ qty($o->stok_fisik) }}</td>
                            <td class="num fw-semibold {{ $o->selisih < 0 ? 'text-danger' : ($o->selisih > 0 ? 'text-success' : '') }}">
                                {{ $o->selisih > 0 ? '+' : '' }}{{ qty($o->selisih) }} {{ $o->barang->satuan }}
                            </td>
                            <td class="num {{ $o->nilai_selisih < 0 ? 'text-danger' : '' }}">{{ rupiah($o->nilai_selisih) }}</td>
                            <td class="small text-muted">{{ $o->keterangan ?: '-' }}</td>
                            <td class="text-end">
                                <x-tombol-hapus :action="route('stok-opname.destroy', $o)" :nama="'opname ' . $o->barang->nama . ' (' . tanggal($o->tanggal) . ')'" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-end">{{ $opnames->links() }}</div>
    @endif
</div>
@endsection
