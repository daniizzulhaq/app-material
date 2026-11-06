<div class="col-12 col-md-4 col-xl-2">
    <label class="form-label small text-muted mb-1">Kategori Klien</label>
    <select name="kategori" class="form-select form-select-sm" onchange="this.form.klien_id.value=''; this.form.submit()">
        <option value="">Semua klien</option>
        @foreach (\App\Models\Klien::KATEGORI as $val => $k)
            <option value="{{ $val }}" @selected(request('kategori') === $val)>Klien {{ $k['label'] }}</option>
        @endforeach
    </select>
</div>
<div class="col-12 col-md-4 col-xl-3">
    <label class="form-label small text-muted mb-1">Klien</label>
    <select name="klien_id" class="form-select form-select-sm">
        <option value="">Semua klien (rekap)</option>
        @foreach ($kliens as $k)
            <option value="{{ $k->id }}" @selected(request('klien_id') == $k->id)>{{ $k->nama }} · {{ $k->nama_kategori }}</option>
        @endforeach
    </select>
</div>
