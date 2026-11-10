@extends('layouts.admin')

@section('title', 'Pembayaran')
@section('page-title', 'Pembayaran')

@section('content')
<x-filter-periode>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Jenis</label>
        <select name="jenis" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="klien" @selected(request('jenis') === 'klien')>Dari Klien</option>
            <option value="supplier" @selected(request('jenis') === 'supplier')>Ke Supplier</option>
        </select>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small text-muted mb-1" for="f-supplier">Supplier <span class="fw-normal">· bisa pilih beberapa</span></label>
        <select id="f-supplier" name="supplier_id[]" multiple class="tom-select" placeholder="Semua supplier">
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected(in_array($s->id, ids(request('supplier_id'))))>{{ $s->nama }}</option>
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
    <div class="col-sm-6"><x-stat-card label="Diterima dari Klien" :value="rupiah($ringkasan->masuk)" icon="bi-arrow-down-circle" color="green" /></div>
    <div class="col-sm-6"><x-stat-card label="Dibayar ke Supplier" :value="rupiah($ringkasan->keluar)" icon="bi-arrow-up-circle" color="red" /></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('pembayaran.create', ['jenis' => 'supplier']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-up-circle me-1"></i>Bayar Supplier</a>
        <a href="{{ route('pembayaran.create', ['jenis' => 'klien']) }}" class="btn btn-sm btn-primary"><i class="bi bi-arrow-down-circle me-1"></i>Terima dari Klien</a>
    </div>

    @if ($pembayarans->isEmpty())
        <x-empty-state icon="bi-credit-card" title="Belum ada pembayaran"
                       message="Catat pembayaran dari klien (pelunasan nota) atau ke supplier (nominal nota per hari / hutang pembelian stok)." />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Pihak</th>
                        <th>Dokumen</th>
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
                            <td>
                                @if ($p->jenis === 'klien')
                                    <span class="badge badge-lunas"><i class="bi bi-arrow-down me-1"></i>Klien</span>
                                @else
                                    <span class="badge badge-belum"><i class="bi bi-arrow-up me-1"></i>Supplier</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $p->jenis === 'klien' ? $p->klien?->nama : $p->supplier?->nama }}</td>
                            <td>
                                @if ($p->transaksi)
                                    <a href="{{ route('transaksi.show', $p->transaksi) }}" class="text-decoration-none">{{ $p->transaksi->no_nota }}</a>
                                @elseif ($p->tanggal_nota)
                                    <a href="{{ route('transaksi.index', ['dari' => $p->tanggal_nota->toDateString(), 'sampai' => $p->tanggal_nota->toDateString(), 'supplier_id' => [$p->supplier_id]]) }}"
                                       class="text-decoration-none" title="Lihat nota tanggal ini">{{ $p->dokumen }}</a>
                                @elseif ($p->pembelianStok)
                                    <a href="{{ route('pembelian-stok.show', $p->pembelianStok) }}" class="text-decoration-none">{{ $p->pembelianStok->no_faktur }}</a>
                                @else - @endif
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
