@props(['kategori'])
@php $k = \App\Models\Klien::KATEGORI[$kategori] ?? null; @endphp
@if ($k)
    <span {{ $attributes->merge(['class' => "badge bg-{$k['warna']}-subtle text-{$k['warna']}-emphasis border border-{$k['warna']}-subtle"]) }}>
        <i class="bi {{ $k['icon'] }} me-1"></i>{{ $k['label'] }}
    </span>
@endif
