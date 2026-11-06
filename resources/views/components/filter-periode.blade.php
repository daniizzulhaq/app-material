@props(['dari' => null, 'sampai' => null, 'action' => url()->current()])

{{-- Kartu filter dengan rentang tanggal + slot untuk filter tambahan --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-muted mb-1" for="f-dari">Dari</label>
                <input type="date" id="f-dari" name="dari" value="{{ $dari ?? request('dari') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-muted mb-1" for="f-sampai">Sampai</label>
                <input type="date" id="f-sampai" name="sampai" value="{{ $sampai ?? request('sampai') }}" class="form-control form-control-sm">
            </div>

            {{ $slot }}

            <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                <a href="{{ $action }}" class="btn btn-sm btn-light">Reset</a>
                {{ $aksi ?? '' }}
            </div>
        </form>
    </div>
</div>
