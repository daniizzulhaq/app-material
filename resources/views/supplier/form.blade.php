@extends('layouts.admin')

@php($edit = $supplier->exists)

@section('title', $edit ? 'Edit Supplier' : 'Tambah Supplier')
@section('page-title', $edit ? 'Edit Supplier' : 'Tambah Supplier')
@section('breadcrumb')
    <a href="{{ route('supplier.index') }}">Supplier</a> / {{ $edit ? $supplier->nama : 'Tambah' }}
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $edit ? route('supplier.update', $supplier) : route('supplier.store') }}" novalidate>
                    @csrf
                    @if ($edit) @method('PUT') @endif

                    @include('partials.form-kontak', ['model' => $supplier, 'placeholderNama' => 'cth. UD Sumber Makmur'])

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('supplier.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
