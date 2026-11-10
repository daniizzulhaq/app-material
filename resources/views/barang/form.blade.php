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
                                   class="form-control @error('nama') is-invalid @enderror" placeholder="cth. Gula Pasir 1kg" required autofocus>
                            @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-8">
                            <label for="supplier_id" class="form-label">Nama Supplier</label>
                            <select id="supplier_id" name="supplier_id" class="tom-select @error('supplier_id') is-invalid @enderror" placeholder="Pilih supplier...">
                                <option value="">— Tanpa supplier —</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(old('supplier_id', $barang->supplier_id) == $s->id)>{{ $s->nama }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Otomatis terisi saat barang dipilih di nota.
                                @if ($suppliers->isEmpty())
                                    <a href="{{ route('supplier.create') }}">Tambah supplier dulu</a>.
                                @endif
                            </div>
                            @error('supplier_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="satuan" class="form-label">Satuan <span class="text-danger">*</span></label>
                            <input type="text" id="satuan" name="satuan" value="{{ old('satuan', $barang->satuan) }}" maxlength="20" list="daftar-satuan"
                                   class="form-control @error('satuan') is-invalid @enderror" placeholder="kg / pcs / dus..." required>
                            <datalist id="daftar-satuan">
                                @foreach (\App\Models\Barang::SATUAN as $s) <option value="{{ $s }}"> @endforeach
                            </datalist>
                            @error('satuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="harga_jual_default" class="form-label">Harga Jual <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="harga_jual_default" name="harga_jual_default" inputmode="numeric" autocomplete="off"
                                       value="{{ old('harga_jual_default', $edit ? input_uang($barang->harga_jual_default) : '') }}"
                                       class="form-control text-end input-uang @error('harga_jual_default') is-invalid @enderror" placeholder="0" required>
                                @error('harga_jual_default') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="harga_beli_default" class="form-label">Harga Beli</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="harga_beli_default" name="harga_beli_default" inputmode="numeric" autocomplete="off"
                                       value="{{ old('harga_beli_default', $edit ? input_uang($barang->harga_beli_default) : '') }}"
                                       class="form-control text-end input-uang @error('harga_beli_default') is-invalid @enderror" placeholder="0">
                                @error('harga_beli_default') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">Otomatis terisi di nota untuk barang dari supplier.</div>
                        </div>
                    </div>

                    <div class="alert alert-light border small mt-4 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Barang ini <strong>tidak otomatis masuk Data Stok</strong>. Stok hanya bertambah lewat
                        <a href="{{ route('pembelian-stok.create') }}">Pembelian Stok</a>.
                        @if ($edit && $barang->mutasis()->exists())
                            <span class="d-block mt-1">Stok saat ini <strong>{{ qty($barang->stok) }} {{ $barang->satuan }}</strong> · HPP rata-rata <strong>{{ rupiah($barang->hpp) }}</strong></span>
                        @endif
                    </div>

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
