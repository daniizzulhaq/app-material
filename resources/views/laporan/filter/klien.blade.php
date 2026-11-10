<div class="col-12 col-md-4 col-xl-2">
    <label class="form-label small text-muted mb-1" for="f-kategori">Kategori Klien</label>
    <select id="f-kategori" name="kategori" class="form-select form-select-sm"
            onchange="document.getElementById('f-klien').tomselect?.clear(true); this.form.submit()">
        <option value="">Semua klien</option>
        @foreach (\App\Models\Klien::KATEGORI as $val => $k)
            <option value="{{ $val }}" @selected(request('kategori') === $val)>Klien {{ $k['label'] }}</option>
        @endforeach
    </select>
</div>
@include('laporan.filter._klien')
@include('laporan.filter._supplier')
