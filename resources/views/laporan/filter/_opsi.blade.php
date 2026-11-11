{{--
    Opsi tampilan laporan (seperti web sayur): format qty, mode harga, tampilkan/sembunyikan klien.
    Variabel: $opsi; opsional $tanpaQty (sembunyikan format qty), $tanpaKlien (sembunyikan opsi klien), $modeHarga (daftar mode)
--}}
@php $modeHarga ??= \App\Support\OpsiLaporan::MODE_HARGA; @endphp

@unless ($tanpaQty ?? false)
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1" for="f-qty">Format Qty</label>
        <select id="f-qty" name="qty" class="form-select form-select-sm"
                title="Barang yang dipesan lebih dari sekali di tanggal yang sama · Total: 2 sak · 1 + 1: 1 + 1 sak">
            @foreach (\App\Support\OpsiLaporan::FORMAT_QTY as $val => $label)
                <option value="{{ $val }}" @selected($opsi->formatQty === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
@endunless
<div class="col-6 col-md-3 col-xl-2">
    <label class="form-label small text-muted mb-1" for="f-harga">Mode Harga</label>
    <select id="f-harga" name="harga" class="form-select form-select-sm"
            title="Lengkap: beli, jual & laba · Jual saja: untuk dikirim ke klien · Beli saja: untuk supplier · Tanpa harga: hanya barang & qty">
        @foreach ($modeHarga as $val => $label)
            <option value="{{ $val }}" @selected($opsi->modeHarga === $val)>{{ $label }}</option>
        @endforeach
    </select>
</div>
@unless ($tanpaKlien ?? false)
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1" for="f-tampil-klien">Nama Klien</label>
        <select id="f-tampil-klien" name="klien" class="form-select form-select-sm"
                title="Tampilkan: dipisah per klien · Sembunyikan: qty barang dari semua klien digabung">
            <option value="tampil" @selected($opsi->tampilKlien)>Tampilkan</option>
            <option value="sembunyi" @selected(! $opsi->tampilKlien)>Sembunyikan</option>
        </select>
    </div>
@endunless
