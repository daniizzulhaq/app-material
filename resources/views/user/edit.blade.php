@extends('layouts.admin')

@section('title', 'Pengaturan Akun')
@section('page-title', 'Pengaturan Akun')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="card-title"><i class="bi bi-person me-2 text-primary"></i>Profil</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('user.update') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-4">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="150"
                               class="form-control @error('email') is-invalid @enderror" required autocomplete="username">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Email ini dipakai untuk login.</div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Profil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="card-title"><i class="bi bi-shield-lock me-2 text-primary"></i>Ganti Password</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('user.password') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="password_lama" class="form-label">Password Lama <span class="text-danger">*</span></label>
                        <input type="password" id="password_lama" name="password_lama" autocomplete="current-password"
                               class="form-control @error('password_lama', 'password') is-invalid @enderror" required>
                        @error('password_lama', 'password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" id="password" name="password" autocomplete="new-password"
                               class="form-control @error('password', 'password') is-invalid @enderror" required>
                        @error('password', 'password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Minimal 8 karakter.</div>
                    </div>
                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" required>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i>Ganti Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
