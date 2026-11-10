{{-- Pilih supplier (bisa lebih dari satu) --}}
<div class="col-12 col-md-6 col-xl-3">
    <label class="form-label small text-muted mb-1" for="f-supplier">Supplier <span class="fw-normal">· bisa pilih beberapa</span></label>
    <select id="f-supplier" name="supplier_id[]" multiple class="tom-select" placeholder="Semua supplier">
        @foreach ($suppliers as $s)
            <option value="{{ $s->id }}" @selected(in_array($s->id, $supplierIds))>{{ $s->nama }}</option>
        @endforeach
    </select>
</div>
