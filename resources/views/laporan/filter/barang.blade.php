<div class="col-12 col-md-4 col-xl-2">
    <label class="form-label small text-muted mb-1" for="f-barang">Barang</label>
    <select id="f-barang" name="barang_id" class="form-select form-select-sm">
        <option value="">Semua barang</option>
        @foreach ($barangs as $b)
            <option value="{{ $b->id }}" @selected(request('barang_id') == $b->id)>{{ $b->nama }}</option>
        @endforeach
    </select>
</div>
@include('laporan.filter._klien')
@include('laporan.filter._supplier')
@include('laporan.filter._opsi')
