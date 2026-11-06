<div class="col-12 col-md-4 col-xl-3">
    <label class="form-label small text-muted mb-1">Barang</label>
    <select name="barang_id" class="form-select form-select-sm">
        <option value="">Semua barang</option>
        @foreach ($barangs as $b)
            <option value="{{ $b->id }}" @selected(request('barang_id') == $b->id)>{{ $b->nama }}</option>
        @endforeach
    </select>
</div>
