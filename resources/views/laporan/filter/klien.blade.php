<div class="col-12 col-md-4 col-xl-3">
    <label class="form-label small text-muted mb-1">Klien</label>
    <select name="klien_id" class="form-select form-select-sm">
        <option value="">Semua klien (rekap)</option>
        @foreach ($kliens as $k)
            <option value="{{ $k->id }}" @selected(request('klien_id') == $k->id)>{{ $k->nama }}</option>
        @endforeach
    </select>
</div>
