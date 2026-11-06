@extends('layouts.admin')

@section('title', 'Stok Opname Baru')
@section('page-title', 'Stok Opname Baru')
@section('breadcrumb')
    <a href="{{ route('stok-opname.index') }}">Stok Opname</a> / Baru
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('stok-opname.store') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                               class="form-control @error('tanggal') is-invalid @enderror" required>
                        @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="barang_id" class="form-label">Barang <span class="text-danger">*</span></label>
                        <select id="barang_id" name="barang_id" class="tom-select @error('barang_id') is-invalid @enderror" placeholder="Cari barang..." required>
                            <option value="">Cari barang...</option>
                            @foreach ($barangs as $b)
                                <option value="{{ $b->id }}" data-stok="{{ (float) $b->stok }}" data-hpp="{{ (float) $b->hpp }}" data-satuan="{{ $b->satuan }}"
                                        @selected(old('barang_id', $barangTerpilih) == $b->id)>{{ $b->nama }}</option>
                            @endforeach
                        </select>
                        @error('barang_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Stok Sistem</label>
                            <input type="text" id="stok_sistem" class="form-control text-end input-locked" readonly tabindex="-1" value="-">
                        </div>
                        <div class="col-6">
                            <label for="stok_fisik" class="form-label">Stok Fisik <span class="text-danger">*</span></label>
                            <input type="number" id="stok_fisik" name="stok_fisik" value="{{ old('stok_fisik') }}" min="0" step="any"
                                   class="form-control text-end @error('stok_fisik') is-invalid @enderror" required>
                            @error('stok_fisik') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="alert alert-light border d-flex justify-content-between mb-3" id="ringkasan-selisih">
                        <div><small class="text-muted d-block">Selisih</small><strong id="selisih">-</strong></div>
                        <div class="text-end"><small class="text-muted d-block">Nilai selisih (selisih × HPP)</small><strong id="nilai-selisih">-</strong></div>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan') }}" maxlength="255" class="form-control" placeholder="cth. sak sobek, hilang, salah hitung">
                    </div>

                    <p class="small text-muted"><i class="bi bi-info-circle me-1"></i>Opname mengganti stok sistem dengan stok fisik dan tercatat sebagai <em>penyesuaian</em> di kartu stok. Tidak memengaruhi cashflow.</p>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('stok-opname.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Opname</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
    const select = document.getElementById('barang_id');
    const fisik = document.getElementById('stok_fisik');

    function hitung() {
        const opt = select.selectedOptions[0];
        if (!opt || !opt.value) {
            document.getElementById('stok_sistem').value = '-';
            return;
        }
        const stok = parseFloat(opt.dataset.stok) || 0;
        const hpp = parseFloat(opt.dataset.hpp) || 0;
        document.getElementById('stok_sistem').value = `${formatQty(stok)} ${opt.dataset.satuan}`;

        if (fisik.value === '') {
            document.getElementById('selisih').textContent = '-';
            document.getElementById('nilai-selisih').textContent = '-';
            return;
        }
        const selisih = Math.round(((parseFloat(fisik.value) || 0) - stok) * 100) / 100;
        const el = document.getElementById('selisih');
        el.textContent = `${selisih > 0 ? '+' : ''}${formatQty(selisih)} ${opt.dataset.satuan}`;
        el.className = selisih < 0 ? 'text-danger' : (selisih > 0 ? 'text-success' : '');
        const nilai = document.getElementById('nilai-selisih');
        nilai.textContent = formatRupiah(selisih * hpp);
        nilai.className = selisih < 0 ? 'text-danger' : '';
    }

    select.addEventListener('change', hitung);
    fisik.addEventListener('input', hitung);
    hitung();
</script>
@endpush
