@props([
    'icon' => 'bi-inbox',
    'title' => 'Belum ada data',
    'message' => null,
])

{{-- Tampilan ramah ketika data kosong. Slot opsional untuk tombol aksi. --}}
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <div class="empty-icon"><i class="bi {{ $icon }}"></i></div>
    <h6 class="mb-1">{{ $title }}</h6>
    @if ($message)
        <p class="mb-3 small">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
