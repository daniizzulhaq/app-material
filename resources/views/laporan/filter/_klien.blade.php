{{-- Pilih klien (bisa lebih dari satu). 1 klien di laporan klien = rincian per nota. --}}
<div class="col-12 col-md-6 col-xl-3">
    <label class="form-label small text-muted mb-1" for="f-klien">Klien <span class="fw-normal">· bisa pilih beberapa</span></label>
    <select id="f-klien" name="klien_id[]" multiple class="tom-select" placeholder="Semua klien">
        @foreach ($kliens as $k)
            <option value="{{ $k->id }}" @selected(in_array($k->id, $klienIds))>{{ $k->nama }} · {{ $k->nama_kategori }}</option>
        @endforeach
    </select>
</div>
