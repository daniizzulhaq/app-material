@extends('layouts.admin')

@section('title', 'Riwayat Hapus')
@section('page-title', 'Riwayat Hapus')

@section('content')
<div class="alert alert-light border d-flex gap-2 align-items-start small">
    <i class="bi bi-info-circle text-primary fs-5"></i>
    <div>
        Semua data yang dihapus tersimpan di sini dan bisa <strong>dipulihkan</strong> kapan saja.
        Saat dipulihkan, stok, HPP, cashflow, dan status bayar dihitung ulang otomatis.
        Data yang bergantung pada data lain (mis. nota butuh kliennya) perlu dipulihkan berurutan.
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form method="GET" class="d-flex flex-wrap gap-2" role="search">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari nama / no. dokumen..." style="min-width: 200px">
            <select name="jenis" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">Semua jenis</option>
                @foreach (\App\Models\RiwayatHapus::JENIS as $val => $j)
                    <option value="{{ $val }}" @selected(request('jenis') === $val)>{{ $j['label'] }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <div class="btn-group btn-group-sm" role="group">
            @foreach (['terhapus' => 'Terhapus (' . $jumlahTerhapus . ')', 'dipulihkan' => 'Sudah dipulihkan', 'semua' => 'Semua'] as $val => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $val, 'page' => null]) }}"
                   class="btn {{ $status === $val ? 'btn-primary' : 'btn-light' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if ($riwayats->isEmpty())
        <x-empty-state icon="bi-clock-history" title="Riwayat kosong"
                       message="{{ $status === 'dipulihkan' ? 'Belum ada data yang dipulihkan.' : 'Belum ada data yang dihapus.' }}" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle" data-mobile-judul="2">
                <thead>
                    <tr>
                        <th>Dihapus</th>
                        <th>Data</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($riwayats as $r)
                        <tr>
                            <td class="text-nowrap small">{{ tanggal($r->created_at) }} <span class="text-muted">{{ $r->created_at->format('H:i') }}</span></td>
                            <td class="fw-semibold">{{ $r->label }}</td>
                            <td><span class="badge text-bg-light border"><i class="bi {{ $r->icon }} me-1"></i>{{ $r->nama_jenis }}</span></td>
                            <td class="small text-muted">{{ $r->ringkasan ?: '-' }}</td>
                            <td>
                                @if ($r->dipulihkan_at)
                                    <span class="badge bg-success-subtle text-success">Dipulihkan {{ tanggal($r->dipulihkan_at) }}</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">Terhapus</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @unless ($r->dipulihkan_at)
                                    <form method="POST" action="{{ route('riwayat.pulihkan', $r) }}" class="d-inline form-pulihkan" data-nama="{{ $r->label }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan</button>
                                    </form>
                                @endunless
                                <x-tombol-hapus permanen :action="route('riwayat.destroy', $r)" :nama="'riwayat ' . $r->label . ' secara permanen'" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            @if ($status !== 'terhapus')
                <form method="POST" action="{{ route('riwayat.kosongkan') }}" class="form-hapus" data-permanen="1" data-nama="semua riwayat yang sudah dipulihkan">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash me-1"></i>Bersihkan yang sudah dipulihkan</button>
                </form>
            @else
                <span></span>
            @endif
            {{ $riwayats->links() }}
        </div>
    @endif
</div>
@endsection
