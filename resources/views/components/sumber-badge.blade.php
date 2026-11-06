@props(['sumber'])

@if ($sumber === 'stok')
    <span {{ $attributes->merge(['class' => 'badge badge-stok']) }}><i class="bi bi-boxes me-1"></i>Stok</span>
@else
    <span {{ $attributes->merge(['class' => 'badge badge-supplier']) }}><i class="bi bi-truck me-1"></i>Supplier</span>
@endif
