@extends('layouts.admin')

@php($edit = $transaksi->exists)

@section('title', $edit ? 'Edit Nota' : 'Nota Baru')
@section('page-title', $edit ? 'Edit ' . $transaksi->label() : 'Nota Penjualan Baru')
@section('breadcrumb')
    <a href="{{ route('transaksi.index') }}">Rekapan</a> / {{ $edit ? 'Edit' : 'Baru' }}
@endsection

@section('content')
<form method="POST" action="{{ $edit ? route('transaksi.update', $transaksi) : route('transaksi.store') }}" id="form-nota" novalidate>
    @csrf
    @if ($edit) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', $transaksi->tanggal?->toDateString()) }}"
                           class="form-control @error('tanggal') is-invalid @enderror" required>
                    @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="klien_id" class="form-label">Klien <span class="text-danger">*</span></label>
                    <select id="klien_id" name="klien_id" class="tom-select @error('klien_id') is-invalid @enderror" placeholder="Pilih klien..." required>
                        <option value="">Pilih klien...</option>
                        @foreach ($kliens as $k)
                            <option value="{{ $k->id }}" @selected(old('klien_id', $transaksi->klien_id) == $k->id)>{{ $k->nama }} · {{ $k->nama_kategori }}</option>
                        @endforeach
                    </select>
                    @error('klien_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="keterangan" class="form-label">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $transaksi->keterangan) }}" class="form-control" placeholder="cth. kirim pagi">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h6 class="card-title">Item Nota <span class="small text-muted fw-normal">· supplier & harga terisi otomatis dari barang, baris kosong diabaikan</span></h6>
        </div>
        @error('items') <div class="alert alert-danger m-3 mb-0 py-2">{{ $message }}</div> @enderror
        <table class="table align-middle mb-0 tabel-nota tabel-item" id="tabel-item">
            <thead>
                <tr>
                    <th class="col-supplier">Supplier / Sumber</th>
                    <th class="col-barang">Barang</th>
                    <th class="col-qty text-center">Qty</th>
                    <th class="col-harga text-center">Harga Jual</th>
                    <th class="col-harga text-center">Harga Beli</th>
                    <th class="col-satuan text-center">Satuan</th>
                    <th class="col-total text-center">Total Jual</th>
                    <th class="col-total text-center">Total Beli</th>
                    <th class="col-aksi"></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="item-kosong" class="d-none">
            <x-empty-state icon="bi-cart" title="Belum ada item" message="Tambahkan item dari supplier atau dari stok gudang." class="py-4" />
        </div>
        <div class="card-body border-top">
            {{-- Tambah beberapa baris sekaligus --}}
            <div class="d-flex flex-wrap align-items-center gap-2">
                <label for="jumlah-baris" class="small text-muted mb-0">Tambah</label>
                <input type="text" id="jumlah-baris" class="form-control form-control-sm text-center" value="1" inputmode="numeric"
                       autocomplete="off" style="width: 64px" aria-label="Jumlah baris yang ditambah" title="Jumlah baris yang ditambah">
                <span class="small text-muted">baris</span>
                <button type="button" class="btn btn-sm btn-outline-primary" data-tambah="supplier"><i class="bi bi-truck me-1"></i>+ Dari Supplier</button>
                <button type="button" class="btn btn-sm btn-outline-success" data-tambah="stok"><i class="bi bi-boxes me-1"></i>+ Dari Stok</button>
            </div>
        </div>
    </div>

    {{-- Ringkasan total: menempel tetap di bawah layar (tidak ikut naik-turun saat digulir) --}}
    <div class="card ringkasan-bawah" id="ringkasan-bawah">
        <div class="card-body py-2 py-md-3">
            <div class="row g-3 align-items-center">
                <div class="col-6 col-md">
                    <div class="stat-label mb-0">Total Jual</div>
                    <div class="fs-5 fw-bold" id="sum-jual">Rp 0</div>
                </div>
                <div class="col-6 col-md">
                    <div class="stat-label mb-0">Total Beli (Modal)</div>
                    <div class="fs-5 fw-bold text-secondary" id="sum-modal">Rp 0</div>
                </div>
                <div class="col-6 col-md">
                    <div class="stat-label mb-0">Laba</div>
                    <div class="fs-5 fw-bold text-success" id="sum-laba">Rp 0</div>
                </div>
                <div class="col-6 col-md">
                    <div class="stat-label mb-0">Belanja Supplier</div>
                    <div class="fs-5 fw-bold text-primary" id="sum-belanja">Rp 0</div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2 justify-content-end">
                    <a href="{{ $edit ? route('transaksi.show', $transaksi) : route('transaksi.index') }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Nota</button>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Template baris item (diisi oleh JavaScript) --}}
<template id="template-baris">
    <tr class="baris-item">
        <td class="col-supplier kolom-penuh" data-label="Supplier / Sumber">
            <input type="hidden" data-name="sumber" class="input-sumber">
            <input type="hidden" data-name="supplier_id" class="input-supplier">
            <select class="form-select pilih-sumber" aria-label="Supplier / sumber">
                <option value="">Pilih supplier...</option>
                <option value="stok">🏠 Stok Gudang</option>
                <optgroup label="Supplier">
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->nama }}</option>
                    @endforeach
                </optgroup>
            </select>
            <div class="invalid-feedback d-block" data-err="supplier_id"></div>
        </td>
        <td class="col-barang kolom-barang" data-label="Barang">
            <select data-name="barang_id" class="pilih-barang" placeholder="Cari barang..."></select>
            <div class="invalid-feedback d-block" data-err="barang_id"></div>
        </td>
        <td class="col-qty" data-label="Qty">
            <input type="text" data-name="qty" inputmode="decimal" autocomplete="off" class="form-control text-end input-qty" aria-label="Qty">
            <small class="info-stok text-muted d-none"></small>
            <div class="invalid-feedback d-block" data-err="qty"></div>
        </td>
        <td class="col-harga" data-label="Harga Jual">
            <input type="text" data-name="harga_jual" inputmode="numeric" autocomplete="off" class="form-control text-end input-uang input-jual" aria-label="Harga jual">
            <div class="invalid-feedback d-block" data-err="harga_jual"></div>
        </td>
        <td class="col-harga" data-label="Harga Beli">
            <input type="text" data-name="harga_beli" inputmode="numeric" autocomplete="off" class="form-control text-end input-uang input-beli" aria-label="Harga beli">
            <small class="info-hpp text-muted d-none"><i class="bi bi-lock-fill"></i> HPP</small>
            <div class="invalid-feedback d-block" data-err="harga_beli"></div>
        </td>
        <td class="col-satuan" data-label="Satuan">
            <input type="text" class="form-control text-center satuan" readonly tabindex="-1" aria-label="Satuan">
        </td>
        <td class="col-total" data-label="Total Jual">
            <input type="text" class="form-control text-end fw-semibold sub-jual" readonly tabindex="-1" aria-label="Total jual">
        </td>
        <td class="col-total" data-label="Total Beli">
            <input type="text" class="form-control text-end sub-beli" readonly tabindex="-1" aria-label="Total beli">
        </td>
        <td class="col-aksi kolom-hapus"><button type="button" class="btn btn-light text-danger hapus-baris" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
@endsection

@push('styles')
<style>
    /* Total nota selalu di bawah layar; tinggi kartu disalin ke padding form (lihat script) agar isi tidak tertutup */
    .ringkasan-bawah { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1020; margin: 0; border-radius: 14px 14px 0 0;
        box-shadow: 0 -4px 18px rgba(16, 24, 40, .12); }
    @media (min-width: 992px) { .ringkasan-bawah { left: var(--sidebar-width); } }
    .ts-dropdown { z-index: 1040; } /* dropdown barang tetap di atas kartu total */
    @media (max-width: 767.98px) {
        .ringkasan-bawah .stat-label { font-size: .7rem; }
        .ringkasan-bawah .fs-5 { font-size: 1rem !important; }
    }
    /* Tabel selebar kartu (table-layout fixed) agar tidak perlu digeser ke samping */
    .tabel-nota { width: 100%; table-layout: fixed; }
    .tabel-nota > :is(thead, tbody) > tr > :is(th, td) { padding: .45rem .3rem; vertical-align: top; }
    .tabel-nota > :is(thead, tbody) > tr > :is(th, td):first-child { padding-left: 1rem; }
    .tabel-nota > :is(thead, tbody) > tr > :is(th, td):last-child { padding-right: .9rem; }
    .tabel-nota > thead > tr > th { vertical-align: middle; white-space: normal; line-height: 1.2; }
    .tabel-nota .col-supplier { width: 15%; }
    .tabel-nota .col-barang { width: 20%; }
    .tabel-nota .col-qty { width: 8%; }
    .tabel-nota .col-harga { width: 11%; }
    .tabel-nota .col-satuan { width: 7%; }
    .tabel-nota .col-total { width: 11.5%; }
    .tabel-nota .col-aksi { width: 5%; text-align: right; }
    .tabel-nota .form-control, .tabel-nota .form-select { padding-left: .5rem; padding-right: .5rem; min-width: 0; }
    .tabel-nota .form-select { padding-right: 1.8rem; text-overflow: ellipsis; }
    .tabel-nota .form-control[readonly]:not(.input-locked) { background: #f6f7fb; border-color: #e6e8f0; }
    .tabel-nota .ts-wrapper, .tabel-nota .ts-control { width: 100%; max-width: 100%; min-width: 0; }
    .tabel-nota .ts-control { flex-wrap: nowrap; overflow: hidden; }
    .tabel-nota .ts-control > .item { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; max-width: 100%; }
    .tabel-nota .ts-wrapper.single.input-active .ts-control > .item { display: none; }
    .tabel-nota .hapus-baris { padding: .4rem .55rem; }
    .tabel-nota tr.sumber-stok > td:first-child { box-shadow: inset 3px 0 0 #22c55e; }
    .tabel-nota tr.sumber-supplier > td:first-child { box-shadow: inset 3px 0 0 #4f6ef7; }
    .tabel-nota .pilih-sumber.is-stok { background-color: #ecfdf3; color: #157f3d; font-weight: 600; }
    @media (max-width: 767.98px) {
        .tabel-nota > tbody > tr > td { width: auto !important; }
        .tabel-nota td.col-supplier { padding-right: 2.75rem !important; }
        .tabel-nota td.kolom-barang { padding-right: 0 !important; }
        .tabel-nota .form-control, .tabel-nota .form-select { font-size: 16px; } /* cegah auto-zoom iOS */
    }
</style>
@endpush

@push('scripts')
<script type="module">
    const BARANG = @json($barangs->keyBy('id'));
    const DAFTAR_BARANG = Object.values(BARANG);
    const HPP_TERKUNCI = @json($hppTerkunci);
    const ITEMS = @json((object) $items);
    const ERRORS = @json($errors->getMessages());
    const MAKS_BARIS = 200;

    const tbody = document.querySelector('#tabel-item tbody');
    const template = document.getElementById('template-baris');
    let indeks = 0;

    const angka = (n) => (n ? Math.round(n).toLocaleString('id-ID') : '');
    const angkaDesimal = (n) => (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    // Qty di input: tanpa titik ribuan (1000 bukan 1.000) agar tidak terbaca sebagai desimal
    const angkaQty = (n) => (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2, useGrouping: false });
    const hppUntuk = (barangId) => {
        if (HPP_TERKUNCI[barangId] !== undefined) return parseFloat(HPP_TERKUNCI[barangId]);
        return BARANG[barangId] ? BARANG[barangId].hpp : 0;
    };
    const isStok = (tr) => tr.querySelector('.input-sumber').value === 'stok';

    /** Isi pilihan barang: dari supplier = semua barang (dikelompokkan per supplier), dari stok = barang di data stok saja. */
    function isiPilihanBarang(tr) {
        const ts = tr.querySelector('.pilih-barang').tomselect;
        const stok = isStok(tr);
        const nilai = ts.getValue();

        ts.clear(true);
        ts.clearOptions();
        ts.clearOptionGroups();

        const daftar = stok ? DAFTAR_BARANG.filter((b) => b.di_stok) : DAFTAR_BARANG;
        const grup = new Map();
        daftar.forEach((b) => {
            const key = stok ? 'stok' : (b.supplier_id ? `s${b.supplier_id}` : 'zz');
            if (!grup.has(key)) grup.set(key, stok ? 'Stok Gudang' : (b.supplier || 'Tanpa supplier'));
        });
        [...grup.entries()]
            .sort((a, b) => (a[0] === 'zz') - (b[0] === 'zz') || a[1].localeCompare(b[1]))
            .forEach(([key, label]) => ts.addOptionGroup(key, { label }));
        daftar.forEach((b) => ts.addOption({
            value: String(b.id),
            text: stok ? `${b.label} (sisa ${angkaDesimal(b.stok)})` : b.label,
            optgroup: stok ? 'stok' : (b.supplier_id ? `s${b.supplier_id}` : 'zz'),
        }));
        ts.refreshOptions(false);

        if (nilai && daftar.some((b) => String(b.id) === String(nilai))) ts.setValue(nilai, true);
    }

    /** Terapkan pilihan kolom Supplier / Sumber ke input tersembunyi sumber & supplier_id. */
    function terapkanSumber(tr) {
        const v = tr.querySelector('.pilih-sumber').value;
        tr.querySelector('.input-sumber').value = v === 'stok' ? 'stok' : 'supplier';
        tr.querySelector('.input-supplier').value = v === 'stok' ? '' : v;
    }

    function tambahBaris(item = {}, key = null) {
        const i = key ?? indeks;
        indeks = Math.max(indeks, Number(i) + 1);

        const tr = template.content.firstElementChild.cloneNode(true);
        tr.querySelectorAll('[data-name]').forEach((el) => { el.name = `items[${i}][${el.dataset.name}]`; });

        const sumber = item.sumber === 'stok' ? 'stok' : 'supplier';
        const pilihSumber = tr.querySelector('.pilih-sumber');
        pilihSumber.value = sumber === 'stok' ? 'stok' : (item.supplier_id ? String(item.supplier_id) : '');
        if (sumber === 'supplier' && item.supplier_id) pilihSumber.dataset.auto = '0';
        terapkanSumber(tr);

        // Nilai awal dari database / old input sudah berupa angka murni (70000.00)
        const ada = (v) => v !== undefined && v !== null && v !== '';
        if (ada(item.qty)) tr.querySelector('.input-qty').value = angkaQty(Number(item.qty));
        if (ada(item.harga_jual)) {
            tr.querySelector('.input-jual').value = angka(Number(item.harga_jual)) || '0';
            tr.querySelector('.input-jual').dataset.auto = '0';
        }
        if (sumber === 'supplier' && ada(item.harga_beli)) {
            tr.querySelector('.input-beli').value = angka(Number(item.harga_beli)) || '0';
            tr.querySelector('.input-beli').dataset.auto = '0';
        }

        // Pesan error validasi server per baris
        tr.querySelectorAll('[data-err]').forEach((el) => {
            const pesan = ERRORS[`items.${i}.${el.dataset.err}`];
            if (!pesan) return;
            el.textContent = pesan[0];
            const target = el.dataset.err === 'supplier_id' ? pilihSumber : tr.querySelector(`[data-name="${el.dataset.err}"]`);
            target?.classList.add('is-invalid', 'server-invalid');
        });

        tbody.appendChild(tr);

        const selectBarang = tr.querySelector('.pilih-barang');
        new TomSelect(selectBarang, {
            placeholder: 'Cari barang...', allowEmptyOption: true, dropdownParent: 'body', closeAfterSelect: true,
            maxOptions: 1000, lockOptgroupOrder: true,
            render: { no_results: () => `<div class="no-results">${isStok(tr) ? 'Barang tidak ada di data stok' : 'Barang tidak ditemukan'}</div>` },
        });
        isiPilihanBarang(tr);
        if (item.barang_id) selectBarang.tomselect.setValue(String(item.barang_id), true);

        selectBarang.addEventListener('change', () => pilihBarang(tr));
        pilihSumber.addEventListener('change', () => {
            pilihSumber.dataset.auto = '0';
            pilihSumber.classList.remove('is-invalid');
            const keStok = pilihSumber.value === 'stok';
            const dariStok = isStok(tr);
            terapkanSumber(tr);
            if (keStok !== dariStok) {
                if (!keStok) { tr.querySelector('.input-beli').value = ''; tr.querySelector('.input-beli').dataset.auto = '1'; }
                isiPilihanBarang(tr);
                if (!keStok) isiHargaBeliOtomatis(tr);
            }
            perbaruiBaris(tr);
        });
        tr.querySelector('.input-jual').addEventListener('input', (e) => e.target.dataset.auto = '0');
        tr.querySelector('.input-beli').addEventListener('input', (e) => e.target.dataset.auto = '0');

        perbaruiBaris(tr);
        toggleKosong();
        return tr;
    }

    function isiHargaBeliOtomatis(tr) {
        const b = BARANG[tr.querySelector('.pilih-barang').value];
        const beli = tr.querySelector('.input-beli');
        if (b && !isStok(tr) && (beli.value === '' || beli.dataset.auto !== '0')) {
            beli.value = b.harga_beli ? angka(b.harga_beli) : '';
            beli.dataset.auto = '1';
        }
    }

    /** Barang dipilih -> supplier, harga jual, harga beli & satuan terisi otomatis (tetap bisa diubah). */
    function pilihBarang(tr) {
        const b = BARANG[tr.querySelector('.pilih-barang').value];
        const pilihSumber = tr.querySelector('.pilih-sumber');
        const jual = tr.querySelector('.input-jual');

        if (b) {
            if (jual.value === '' || jual.dataset.auto !== '0') {
                jual.value = b.harga_jual ? angka(b.harga_jual) : '';
                jual.dataset.auto = '1';
            }
            // Nama supplier otomatis mengikuti supplier barang
            if (!isStok(tr) && b.supplier_id && (pilihSumber.value === '' || pilihSumber.dataset.auto !== '0')) {
                pilihSumber.value = String(b.supplier_id);
                pilihSumber.dataset.auto = '1';
                pilihSumber.classList.remove('is-invalid');
                terapkanSumber(tr);
            }
            isiHargaBeliOtomatis(tr);
        }
        perbaruiBaris(tr);
        if (window.matchMedia('(pointer: fine)').matches) tr.querySelector('.input-qty').focus({ preventScroll: true });
    }

    // Atur tampilan baris sesuai sumber (Supplier / Stok)
    function perbaruiBaris(tr) {
        const barangId = tr.querySelector('.pilih-barang').value;
        const b = BARANG[barangId];
        const stok = isStok(tr);
        const beli = tr.querySelector('.input-beli');

        tr.classList.toggle('sumber-stok', stok);
        tr.classList.toggle('sumber-supplier', !stok);
        tr.querySelector('.pilih-sumber').classList.toggle('is-stok', stok);
        tr.querySelector('.satuan').value = b ? b.satuan : '';

        // Sumber stok: harga beli = HPP, dikunci
        beli.readOnly = stok;
        beli.tabIndex = stok ? -1 : 0;
        beli.classList.toggle('input-locked', stok);
        tr.querySelector('.info-hpp').classList.toggle('d-none', !stok);
        if (stok) beli.value = b ? angkaDesimal(hppUntuk(barangId)) : '';

        const infoStok = tr.querySelector('.info-stok');
        infoStok.classList.toggle('d-none', !stok || !b);
        if (stok && b) infoStok.textContent = `Sisa ${angkaDesimal(b.stok)}`;

        hitung();
    }

    function hitung() {
        let jual = 0, modal = 0, belanja = 0;
        const pemakaianStok = {};

        tbody.querySelectorAll('.baris-item').forEach((tr) => {
            const qty = parseQty(tr.querySelector('.input-qty').value);
            const hBeli = parseUang(tr.querySelector('.input-beli').value);
            const hJual = parseUang(tr.querySelector('.input-jual').value);
            const subBeli = Math.round(qty * hBeli * 100) / 100;
            const subJual = Math.round(qty * hJual * 100) / 100;
            const stok = isStok(tr);
            const barangId = tr.querySelector('.pilih-barang').value;

            jual += subJual;
            modal += subBeli;
            if (!stok) belanja += subBeli;
            if (stok && barangId) pemakaianStok[barangId] = (pemakaianStok[barangId] || 0) + qty;

            tr.querySelector('.sub-jual').value = angka(subJual);
            tr.querySelector('.sub-beli').value = angka(subBeli);
        });

        // Tandai qty yang melebihi stok (dijumlah per barang)
        tbody.querySelectorAll('.baris-item').forEach((tr) => {
            const qtyInput = tr.querySelector('.input-qty');
            const info = tr.querySelector('.info-stok');
            const barangId = tr.querySelector('.pilih-barang').value;
            const lebih = isStok(tr) && BARANG[barangId] && pemakaianStok[barangId] > BARANG[barangId].stok + 0.0001;
            qtyInput.classList.toggle('is-invalid', !!lebih || qtyInput.classList.contains('server-invalid'));
            info.classList.toggle('text-danger', !!lebih);
            info.classList.toggle('text-muted', !lebih);
        });

        document.getElementById('sum-jual').textContent = formatRupiah(jual);
        document.getElementById('sum-modal').textContent = formatRupiah(modal);
        const elLaba = document.getElementById('sum-laba');
        elLaba.textContent = formatRupiah(jual - modal);
        elLaba.classList.toggle('text-danger', jual - modal < 0);
        elLaba.classList.toggle('text-success', jual - modal >= 0);
        document.getElementById('sum-belanja').textContent = formatRupiah(belanja);
    }

    function toggleKosong() {
        document.getElementById('item-kosong').classList.toggle('d-none', tbody.children.length > 0);
    }

    // Tambah N baris sekaligus (dari supplier / dari stok)
    const inpJumlah = document.getElementById('jumlah-baris');
    function tambahSesuaiJumlah(sumber) {
        const n = Math.min(50, Math.max(1, parseInt(inpJumlah.value, 10) || 1));
        inpJumlah.value = n;
        if (tbody.children.length + n > MAKS_BARIS) {
            Toast.fire({ icon: 'warning', title: `Maksimal ${MAKS_BARIS} item per nota.` });
            return;
        }
        let pertama = null;
        for (let k = 0; k < n; k++) {
            const tr = tambahBaris({ sumber });
            pertama ??= tr;
        }
        pertama.scrollIntoView({ block: 'nearest' });
        if (window.matchMedia('(pointer: fine)').matches) pertama.querySelector('.pilih-barang').tomselect.focus();
    }
    document.querySelectorAll('[data-tambah]').forEach((btn) => btn.addEventListener('click', () => tambahSesuaiJumlah(btn.dataset.tambah)));
    inpJumlah.addEventListener('input', () => { inpJumlah.value = inpJumlah.value.replace(/\D/g, ''); });
    inpJumlah.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); tambahSesuaiJumlah('supplier'); }
    });

    tbody.addEventListener('click', (e) => {
        if (!e.target.closest('.hapus-baris')) return;
        e.target.closest('tr').remove();
        toggleKosong();
        hitung();
    });
    tbody.addEventListener('input', (e) => {
        if (e.target.classList.contains('input-qty')) {
            e.target.value = e.target.value.replace(/[^\d,.]/g, '');
            e.target.classList.remove('server-invalid');
        }
        hitung();
    });

    document.getElementById('form-nota').addEventListener('submit', (e) => {
        const terisi = [...tbody.querySelectorAll('.baris-item')].filter((tr) => tr.querySelector('.pilih-barang').value);
        if (terisi.length === 0) {
            e.preventDefault();
            Toast.fire({ icon: 'warning', title: 'Tambahkan minimal 1 item.' });
            return;
        }
        if (tbody.querySelector('.info-stok.text-danger:not(.d-none)')) {
            e.preventDefault();
            Toast.fire({ icon: 'error', title: 'Ada qty yang melebihi sisa stok.' });
        }
    });

    // Jaga isi form tidak tertutup kartu total yang menempel di bawah layar
    const formNota = document.getElementById('form-nota');
    const kartuTotal = document.getElementById('ringkasan-bawah');
    const aturJarakBawah = () => { formNota.style.paddingBottom = `${kartuTotal.offsetHeight + 16}px`; };
    new ResizeObserver(aturJarakBawah).observe(kartuTotal);
    aturJarakBawah();

    // Muat baris awal (edit / old input), atau 1 baris kosong
    const keys = Object.keys(ITEMS);
    if (keys.length) {
        keys.forEach((k) => tambahBaris(ITEMS[k], k));
    } else {
        tambahBaris({ sumber: 'supplier' });
    }
</script>
@endpush
