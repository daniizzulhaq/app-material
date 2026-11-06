@extends('layouts.admin')

@php($edit = $klien->exists)

@section('title', $edit ? 'Edit Klien' : 'Tambah Klien')
@section('page-title', $edit ? 'Edit Klien' : 'Tambah Klien')
@section('breadcrumb')
    <a href="{{ route('klien.index') }}">Klien</a> / {{ $edit ? $klien->nama : 'Tambah' }}
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $edit ? route('klien.update', $klien) : route('klien.store') }}" novalidate>
                    @csrf
                    @if ($edit) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label">Kategori Klien <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            @foreach (\App\Models\Klien::KATEGORI as $val => $k)
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="kategori" id="kategori-{{ $val }}" value="{{ $val }}"
                                           @checked(old('kategori', $klien->kategori ?? request('kategori', 'material')) === $val) required>
                                    <label class="btn btn-outline-{{ $k['warna'] }} w-100 py-2 d-flex align-items-center justify-content-center gap-2" for="kategori-{{ $val }}">
                                        <i class="bi {{ $k['icon'] }} fs-5"></i><span class="fw-semibold">Klien {{ $k['label'] }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('kategori') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    @include('partials.form-kontak', ['model' => $klien, 'placeholderNama' => 'cth. Pak Budi - Proyek Ruko'])

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('klien.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
