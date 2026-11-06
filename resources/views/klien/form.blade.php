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
