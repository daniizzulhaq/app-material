@extends('layouts.admin')

@php($edit = $cashflow->exists)

@section('title', $edit ? 'Edit Cashflow' : 'Input Cashflow Manual')
@section('page-title', $edit ? 'Edit Cashflow' : 'Input Cashflow Manual')
@section('breadcrumb')
    <a href="{{ route('cashflow.index') }}">Cashflow</a> / {{ $edit ? 'Edit' : 'Baru' }}
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $edit ? route('cashflow.update', $cashflow) : route('cashflow.store') }}" novalidate>
                    @csrf
                    @if ($edit) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label d-block">Tipe <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tipe" id="tipe-masuk" value="masuk" @checked(old('tipe', $cashflow->tipe) === 'masuk')>
                            <label class="btn btn-outline-success" for="tipe-masuk"><i class="bi bi-arrow-down-circle me-1"></i>Uang Masuk</label>
                            <input type="radio" class="btn-check" name="tipe" id="tipe-keluar" value="keluar" @checked(old('tipe', $cashflow->tipe) === 'keluar')>
                            <label class="btn btn-outline-danger" for="tipe-keluar"><i class="bi bi-arrow-up-circle me-1"></i>Uang Keluar</label>
                        </div>
                        @error('tipe') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', $cashflow->tanggal?->toDateString()) }}"
                                   class="form-control @error('tanggal') is-invalid @enderror" required>
                            @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select id="kategori" name="kategori" class="form-select @error('kategori') is-invalid @enderror">
                                @foreach (\App\Models\Cashflow::KATEGORI_MANUAL as $k)
                                    <option value="{{ $k }}" @selected(old('kategori', $cashflow->kategori) === $k)>{{ \App\Models\Cashflow::KATEGORI[$k] }}</option>
                                @endforeach
                            </select>
                            @error('kategori') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nominal" class="form-label">Nominal <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">Rp</span>
                            <input type="text" id="nominal" name="nominal" inputmode="numeric" autocomplete="off" value="{{ old('nominal', $edit ? input_uang($cashflow->nominal) : '') }}"
                                   class="form-control text-end input-uang @error('nominal') is-invalid @enderror" placeholder="0" required>
                            @error('nominal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
                        <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $cashflow->keterangan) }}" maxlength="255"
                               class="form-control @error('keterangan') is-invalid @enderror" placeholder="cth. Bensin truk, gaji kuli, setoran modal">
                        @error('keterangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('cashflow.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
