<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}?v=shop">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="admin-body">

<div class="admin-wrapper">
    {{-- Sidebar desktop --}}
    <aside class="sidebar d-none d-lg-flex">
        @include('partials.sidebar')
    </aside>

    {{-- Sidebar mobile (offcanvas) --}}
    <div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarMobile" aria-labelledby="sidebarMobileLabel">
        <div class="sidebar w-100">
            @include('partials.sidebar', ['mobile' => true])
        </div>
    </div>

    <main class="content">
        {{-- Header konten --}}
        <header class="content-header">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile" aria-label="Buka menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="page-title mb-0">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('breadcrumb')
                        <div class="page-breadcrumb">@yield('breadcrumb')</div>
                    @endif
                </div>
            </div>
            <div class="header-date d-none d-sm-flex">
                <i class="bi bi-calendar3"></i>
                <span>{{ now()->locale('id')->translatedFormat('l, d M Y') }}</span>
            </div>
        </header>

        <div class="content-body">
            @yield('content')
        </div>

        <footer class="content-footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </footer>
    </main>
</div>

{{-- Data flash untuk toast (dibaca oleh resources/js/app.js) --}}
<div id="flash-data"
     data-success="{{ session('success') }}"
     data-error="{{ session('error') }}"
     data-warning="{{ session('warning') }}"
     hidden></div>

@stack('scripts')
</body>
</html>
