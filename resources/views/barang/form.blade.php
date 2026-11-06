@extends('layouts.admin')

@php($edit = $barang->exists)

@section('title', $edit ? 'Edit Barang' : 'Tambah Barang')
@section('page-title', $edit ? 'Edit Barang' : 'Tambah Barang')
@section('breadcrumb')
    <a href="{{ route('barang.index') }}">Barang</a> / {{ $edit ? $barang->nama : 'Tambah' }}
@endsection

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $edit ? route('barang.update', $barang) : route('barang.store') }}" novalidate>
                    @csrf
                    @if ($edit) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nama" class="form-label">Nama Barang <span class="text-danger">*</span></label>
                            <input type="text" id="nama" name="nama" value="{{ old('nama', $barang->nama) }}" maxlength="150"
                                   class="form-control @error('nama') is-invalid @enderror" placeholder="cth. Semen Gresik 40kg" required autofocus>
                            @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select id="supplier_id" name="supplier_id" class="tom-select @error('supplier_id') is-invalid @enderror" placeholder="Pilih supplier...">
                                <option value="">— Tanpa supplier —</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(old('supplier_id', $barang->supplier_id) == $s->id)>{{ $s->nama }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Supplier tempat barang ini biasa dibeli. Otomatis terisi saat barang dipilih di nota (sumber supplier).
                                @if ($suppliers->isEmpty())
                                    <a href="{{ route('supplier.create') }}">Tambah supplier dulu</a>.
                                @endif
                            </div>
                            @error('supplier_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="satuan" class="form-label">Satuan <span class="text-danger">*</span></label>
                            <input type="text" id="satuan" name="satuan" value="{{ old('satuan', $barang->satuan) }}" maxlength="20" list="daftar-satuan"
                                   class="form-control @error('satuan') is-invalid @enderror" placeholder="sak / m3 / batang..." required>
                            <datalist id="daftar-satuan">
                                @foreach (\App\Models\Barang::SATUAN as $s) <option value="{{ $s }}"> @endforeach
                            </datalist>
                            @error('satuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="harga_jual_default" class="form-label">Harga Jual Default <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">Rp</span>
                                <input type="number" id="harga_jual_default" name="harga_jual_default" min="0" step="any"
                                       value="{{ old('harga_jual_default', $edit ? (float) $barang->harga_jual_default : '') }}"
                                       class="form-control text-end @error('harga_jual_default') is-invalid @enderror" required>
                                @error('harga_jual_default') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="stok_minimum" class="form-label">Stok Minimum <span class="text-danger">*</span></label>
                            <input type="number" id="stok_minimum" name="stok_minimum" min="0" step="any"
                                   value="{{ old('stok_minimum', $edit ? (float) $barang->stok_minimum : 0) }}"
                                   class="form-control text-end @error('stok_minimum') is-invalid @enderror" required>
                            <div class="form-text">Badge "Menipis" muncul jika stok ≤ nilai ini.</div>
                            @error('stok_minimum') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($edit)
                        <div class="alert alert-light border mt-4 mb-0 d-flex gap-3 flex-wrap">
                            <div><small class="text-muted d-block">Stok saat ini</small><strong>{{ qty($barang->stok) }} {{ $barang->satuan }}</strong></div>
                            <div><small class="text-muted d-block">HPP rata-rata</small><strong>{{ rupiah($barang->hpp) }}</strong></div>
                            <div class="small text-muted align-self-center">Stok & HPP berubah otomatis lewat Pembelian Stok, Nota, dan Stok Opname.</div>
                        </div>
                    @else
                        <hr class="my-4">
                        <h6 class="fw-semibold mb-1">Stok Awal <span class="text-muted fw-normal small">(opsional)</span></h6>
                        <p class="small text-muted mb-3">Isi jika barang ini sudah ada di gudang. Akan tercatat di kartu stok sebagai "Stok Awal".</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="stok_awal" class="form-label">Qty Stok Awal</label>
                                <input type="number" id="stok_awal" name="stok_awal" min="0" step="any" value="{{ old('stok_awal') }}"
                                       class="form-control text-end @error('stok_awal') is-invalid @enderror" placeholder="0">
                                @error('stok_awal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="hpp_awal" class="form-label">HPP / Harga Beli per Satuan</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" id="hpp_awal" name="hpp_awal" min="0" step="any" value="{{ old('hpp_awal') }}"
                                           class="form-control text-end @error('hpp_awal') is-invalid @enderror" placeholder="0">
                                    @error('hpp_awal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('barang.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
