@php
    $mobile = $mobile ?? false;
@endphp

<div class="sidebar-brand">
    <div class="brand-logo"><x-logo /></div>
    <div class="brand-text">
        <span class="brand-title">Rekap Groceries<br>dan Material</span>
    </div>
    @if ($mobile)
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    @endif
</div>

<nav class="sidebar-nav">
    @foreach (config('menu') as $section)
        @if ($section['group'])
            <div class="sidebar-group">{{ $section['group'] }}</div>
        @endif

        <ul class="nav flex-column">
            @foreach ($section['items'] as $item)
                @php
                    $tersedia = Route::has($item['route']);
                    $aktif = request()->routeIs($item['active']);
                @endphp
                <li class="nav-item">
                    <a href="{{ $tersedia ? route($item['route']) : '#' }}"
                       @class(['nav-link', 'active' => $aktif, 'disabled' => ! $tersedia])
                       @if ($aktif) aria-current="page" @endif
                       @unless ($tersedia) aria-disabled="true" title="Segera tersedia" @endunless>
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                        @unless ($tersedia)
                            <span class="badge-soon">Segera</span>
                        @endunless
                    </a>
                </li>
            @endforeach
        </ul>
    @endforeach
</nav>

<div class="sidebar-footer">
    <div class="user-box">
        <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
        <div class="user-info">
            <span class="user-name">{{ auth()->user()->name ?? '-' }}</span>
            <span class="user-email">{{ auth()->user()->email ?? '' }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="ms-auto">
            @csrf
            <button type="submit" class="btn btn-logout" title="Keluar" aria-label="Keluar">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</div>
