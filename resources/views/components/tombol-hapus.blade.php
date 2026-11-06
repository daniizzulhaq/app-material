@props(['action', 'nama' => null, 'label' => null, 'permanen' => false])

{{-- Konfirmasi SweetAlert2 ditangani resources/js/app.js (class .form-hapus) --}}
<form method="POST" action="{{ $action }}" class="form-hapus d-inline" data-nama="{{ $nama }}" @if ($permanen) data-permanen="1" @endif>
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => 'btn btn-sm btn-light text-danger']) }} title="Hapus" aria-label="Hapus">
        <i class="bi bi-trash"></i>@if ($label) <span class="ms-1">{{ $label }}</span>@endif
    </button>
</form>
