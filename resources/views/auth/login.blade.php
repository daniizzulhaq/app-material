@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="auth-wrapper">
    {{-- Panel kiri (desktop) --}}
    <div class="auth-side d-none d-lg-flex">
        <div class="d-flex align-items-center gap-2 position-relative" style="z-index:1">
            <div class="brand-logo"><x-logo /></div>
            <span class="brand-title">Rekap Groceries dan Material</span>
        </div>

        <div class="position-relative" style="z-index:1; max-width: 440px">
            <h2 class="mb-3">Kelola penjualan, stok, dan cashflow toko groceries &amp; material dalam satu tempat.</h2>
            <p class="mb-4">Rekap nota harian, pantau stok gudang, dan lihat laba bersih tanpa ribet.</p>

            <div class="auth-feature"><i class="bi bi-receipt"></i><span>Nota penjualan dari supplier langsung maupun stok gudang</span></div>
            <div class="auth-feature"><i class="bi bi-boxes"></i><span>Kartu stok, HPP rata-rata, dan stok opname</span></div>
            <div class="auth-feature"><i class="bi bi-graph-up-arrow"></i><span>Laporan laba, piutang, hutang, dan cashflow</span></div>
        </div>

        <small class="text-secondary position-relative" style="z-index:1">&copy; {{ date('Y') }} {{ config('app.name') }}</small>
    </div>

    {{-- Form login --}}
    <div class="auth-main">
        <div class="auth-card">
            <div class="d-flex d-lg-none align-items-center gap-2 mb-4">
                <div class="brand-logo"><x-logo /></div>
                <span class="fw-bold fs-5">Rekap Groceries dan Material</span>
            </div>

            <h3 class="fw-bold mb-1">Selamat datang 👋</h3>
            <p class="text-muted mb-4">Silakan masuk ke akun admin Anda.</p>

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror"
                               placeholder="admin@admin.com" required autofocus autocomplete="username">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="••••••••" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                    <label class="form-check-label" for="remember">Ingat saya</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const tampil = input.type === 'password';
        input.type = tampil ? 'text' : 'password';
        this.querySelector('i').className = tampil ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
@endsection
