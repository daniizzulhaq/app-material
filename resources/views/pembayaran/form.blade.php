@extends('layouts.admin')

@php($edit = $pembayaran->exists)

@section('title', $edit ? 'Edit Pembayaran' : 'Catat Pembayaran')
@section('page-title', $edit ? 'Edit Pembayaran' : 'Catat Pembayaran')
@section('breadcrumb')
    <a href="{{ route('pembayaran.index') }}">Pembayaran</a> / {{ $edit ? 'Edit' : 'Baru' }}
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $edit ? route('pembayaran.update', $pembayaran) : route('pembayaran.store') }}" novalidate>
                    @csrf
                    @if ($edit) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label d-block">Jenis Pembayaran <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="jenis" id="jenis-klien" value="klien" @checked(old('jenis', $pembayaran->jenis) === 'klien')>
                            <label class="btn btn-outline-success" for="jenis-klien"><i class="bi bi-arrow-down-circle me-1"></i>Dari Klien (masuk)</label>
                            <input type="radio" class="btn-check" name="jenis" id="jenis-supplier" value="supplier" @checked(old('jenis', $pembayaran->jenis) === 'supplier')>
                            <label class="btn btn-outline-danger" for="jenis-supplier"><i class="bi bi-arrow-up-circle me-1"></i>Ke Supplier (keluar)</label>
                        </div>
                    </div>

                    <div class="mb-3 grup-jenis" data-jenis="klien">
                        <label for="transaksi_id" class="form-label">Nota yang Dibayar <span class="text-danger">*</span></label>
                        <select id="transaksi_id" name="transaksi_id" class="tom-select pilih-tagihan @error('transaksi_id') is-invalid @enderror" placeholder="Cari no. nota / klien...">
                            <option value="">Cari no. nota / klien...</option>
                            @foreach ($notas as $n)
                                <option value="{{ $n['id'] }}" data-total="{{ $n['total'] }}" data-sisa="{{ $n['sisa'] }}" @selected(old('transaksi_id', $pembayaran->transaksi_id) == $n['id'])>
                                    {{ $n['label'] }} · sisa {{ rupiah($n['sisa']) }}
                                </option>
                            @endforeach
                        </select>
                        @error('transaksi_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @if ($notas->isEmpty()) <div class="form-text">Tidak ada nota yang belum lunas.</div> @endif
                    </div>

                    <div class="mb-3 grup-jenis" data-jenis="supplier">
                        <label for="pembelian_stok_id" class="form-label">Faktur Pembelian (Hutang) <span class="text-danger">*</span></label>
                        <select id="pembelian_stok_id" name="pembelian_stok_id" class="tom-select pilih-tagihan @error('pembelian_stok_id') is-invalid @enderror" placeholder="Cari no. faktur / supplier...">
                            <option value="">Cari no. faktur / supplier...</option>
                            @foreach ($fakturs as $f)
                                <option value="{{ $f['id'] }}" data-total="{{ $f['total'] }}" data-sisa="{{ $f['sisa'] }}" @selected(old('pembelian_stok_id', $pembayaran->pembelian_stok_id) == $f['id'])>
                                    {{ $f['label'] }} · sisa {{ rupiah($f['sisa']) }}
                                </option>
                            @endforeach
                        </select>
                        @error('pembelian_stok_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @if ($fakturs->isEmpty()) <div class="form-text">Tidak ada pembelian stok dengan hutang yang belum lunas.</div> @endif
                    </div>

                    <div class="alert alert-light border small d-none" id="info-tagihan">
                        Total tagihan <strong id="info-total"></strong> · Sisa <strong class="text-danger" id="info-sisa"></strong>
                        <button type="button" class="btn btn-sm btn-link p-0 ms-2" id="isi-lunas">Isi lunas</button>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', $pembayaran->tanggal?->toDateString()) }}"
                                   class="form-control @error('tanggal') is-invalid @enderror" required>
                            @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="metode" class="form-label">Metode <span class="text-danger">*</span></label>
                            <select id="metode" name="metode" class="form-select @error('metode') is-invalid @enderror">
                                <option value="tunai" @selected(old('metode', $pembayaran->metode) === 'tunai')>Tunai</option>
                                <option value="transfer" @selected(old('metode', $pembayaran->metode) === 'transfer')>Transfer</option>
                            </select>
                            @error('metode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="jumlah" class="form-label">Jumlah <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="jumlah" name="jumlah" min="0" step="any" value="{{ old('jumlah', $edit ? (float) $pembayaran->jumlah : '') }}"
                                   class="form-control text-end @error('jumlah') is-invalid @enderror" required>
                            @error('jumlah') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $pembayaran->keterangan) }}" class="form-control" placeholder="Opsional">
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('pembayaran.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
    const radios = document.querySelectorAll('input[name="jenis"]');
    let sisaAktif = 0;

    function selectAktif() {
        const jenis = document.querySelector('input[name="jenis"]:checked')?.value;
        return document.getElementById(jenis === 'supplier' ? 'pembelian_stok_id' : 'transaksi_id');
    }

    function perbarui() {
        const jenis = document.querySelector('input[name="jenis"]:checked')?.value;
        document.querySelectorAll('.grup-jenis').forEach((g) => g.classList.toggle('d-none', g.dataset.jenis !== jenis));

        const opt = selectAktif().selectedOptions[0];
        const info = document.getElementById('info-tagihan');
        if (opt && opt.value) {
            sisaAktif = parseFloat(opt.dataset.sisa) || 0;
            document.getElementById('info-total').textContent = formatRupiah(opt.dataset.total);
            document.getElementById('info-sisa').textContent = formatRupiah(sisaAktif);
            info.classList.remove('d-none');
        } else {
            info.classList.add('d-none');
        }
    }

    radios.forEach((r) => r.addEventListener('change', perbarui));
    document.querySelectorAll('.pilih-tagihan').forEach((s) => s.addEventListener('change', perbarui));
    document.getElementById('isi-lunas').addEventListener('click', () => document.getElementById('jumlah').value = sisaAktif);
    perbarui();
</script>
@endpush
