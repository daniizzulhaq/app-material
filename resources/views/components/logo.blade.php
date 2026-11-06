@props(['size' => 42])

<img src="{{ asset('logo.svg') }}?v=shop" width="{{ $size }}" height="{{ $size }}"
     {{ $attributes->merge(['class' => 'logo-svg', 'alt' => 'Rekap Groceries dan Material']) }}>
