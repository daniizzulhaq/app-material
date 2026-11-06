<div class="col-12 col-md-4 col-xl-3">
    <label class="form-label small text-muted mb-1">Supplier</label>
    <select name="supplier_id" class="form-select form-select-sm">
        <option value="">Semua supplier</option>
        @foreach ($suppliers as $s)
            <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->nama }}</option>
        @endforeach
    </select>
</div>
