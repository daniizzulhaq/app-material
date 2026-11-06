@props(['status'])

@php
    $label = ['lunas' => 'Lunas', 'sebagian' => 'Sebagian', 'belum' => 'Belum'][$status] ?? ucfirst($status);
@endphp

<span {{ $attributes->merge(['class' => "badge badge-{$status}"]) }}>{{ $label }}</span>
