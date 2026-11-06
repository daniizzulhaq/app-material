@extends('layouts.admin')

@php($edit = $transaksi->exists)

@section('title', $edit ? 'Edit Nota' : 'Nota Baru')
@section('page-title', $edit ? "Edit Nota {$transaksi->no_nota}" : 'Nota Penjualan Baru')
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
                    <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $transaksi->keterangan) }}" class="form-control" placeholder="cth. kirim ke proyek Jl. Melati">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="card-title">Item Nota</h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-tambah="supplier"><i class="bi bi-truck me-1"></i>+ Dari Supplier</button>
                <button type="button" class="btn btn-sm btn-outline-success" data-tambah="stok"><i class="bi bi-boxes me-1"></i>+ Dari Stok</button>
            </div>
        </div>
        @error('items') <div class="alert alert-danger m-3 mb-0 py-2">{{ $message }}</div> @enderror
        <div class="table-responsive">
            <table class="table align-middle mb-0 tabel-nota tabel-item" id="tabel-item">
                <thead>
                    <tr>
                        <th style="width:23%">Barang</th>
                        <th style="width:18%">Sumber / Supplier</th>
                        <th class="text-end" style="width:12%">Qty</th>
                        <th class="text-end" style="width:14%">Harga Beli</th>
                        <th class="text-end" style="width:14%">Harga Jual</th>
                        <th class="text-end" style="width:12%">Subtotal</th>
                        <th style="width:44px"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div id="item-kosong" class="d-none">
            <x-empty-state icon="bi-cart" title="Belum ada item" message="Tambahkan item dari supplier atau dari stok gudang." class="py-4" />
        </div>
    </div>

    {{-- Ringkasan sticky di bawah form --}}
    <div class="card ringkasan-sticky">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-6 col-md">
                    <div class="stat-label mb-0">Total Jual</div>
                    <div class="fs-5 fw-bold" id="sum-jual">Rp 0</div>
                </div>
                <div class="col-6 col-md">
                    <div class="stat-label mb-0">Total Modal</div>
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
        <td class="kolom-barang" data-label="Barang">
            <select data-name="barang_id" class="pilih-barang" placeholder="Cari barang...">
                <option value="">Cari barang...</option>
                @foreach ($barangs as $b)
                    <option value="{{ $b['id'] }}">{{ $b['label'] }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-err="barang_id"></div>
            <div class="mt-1 badge-sumber"></div>
        </td>
        <td class="kolom-penuh" data-label="Sumber / Supplier">
            <select data-name="sumber" class="form-select form-select-sm pilih-sumber mb-1">
                <option value="supplier">Dari Supplier</option>
                <option value="stok">Dari Stok Gudang</option>
            </select>
            <div class="kolom-supplier">
                <select data-name="supplier_id" class="form-select form-select-sm pilih-supplier">
                    <option value="">Pilih supplier...</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="kolom-gudang small text-muted d-none"><i class="bi bi-house-door me-1"></i>Gudang sendiri</div>
            <div class="invalid-feedback d-block" data-err="supplier_id"></div>
        </td>
        <td data-label="Qty">
            <div class="input-group input-group-sm">
                <input type="number" data-name="qty" min="0" step="any" class="form-control text-end input-qty">
                <span class="input-group-text satuan">-</span>
            </div>
            <small class="info-stok text-muted d-none"></small>
            <div class="invalid-feedback d-block" data-err="qty"></div>
        </td>
        <td data-label="Harga Beli">
            <div class="input-group input-group-sm">
                <span class="input-group-text">Rp</span>
                <input type="number" data-name="harga_beli" min="0" step="any" class="form-control text-end input-beli">
            </div>
            <small class="info-hpp text-muted d-none"><i class="bi bi-lock-fill"></i> HPP terkunci</small>
            <div class="invalid-feedback d-block" data-err="harga_beli"></div>
        </td>
        <td data-label="Harga Jual">
            <div class="input-group input-group-sm">
                <span class="input-group-text">Rp</span>
                <input type="number" data-name="harga_jual" min="0" step="any" class="form-control text-end input-jual">
            </div>
            <div class="invalid-feedback d-block" data-err="harga_jual"></div>
        </td>
        <td class="text-end" data-label="Subtotal">
            <div class="fw-semibold sub-jual">Rp 0</div>
            <small class="sub-laba text-muted">laba Rp 0</small>
        </td>
        <td class="kolom-hapus"><button type="button" class="btn btn-sm btn-light text-danger hapus-baris" title="Hapus item"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
@endsection

@push('styles')
<style>
    .ringkasan-sticky { position: sticky; bottom: 1rem; z-index: 20; box-shadow: 0 -2px 18px rgba(16, 24, 40, .10); }
    .tabel-nota tr.sumber-stok td:first-child { box-shadow: inset 3px 0 0 #22c55e; }
    .tabel-nota tr.sumber-supplier td:first-child { box-shadow: inset 3px 0 0 #4f6ef7; }
    .tabel-nota { min-width: 1060px; table-layout: fixed; }
    .tabel-nota td { vertical-align: top; padding-left: .5rem; padding-right: .5rem; }
    .tabel-nota th { padding-left: .5rem; padding-right: .5rem; }
</style>
@endpush

@push('scripts')
<script type="module">
    const BARANG = @json($barangs->keyBy('id'));
    const HPP_TERKUNCI = @json($hppTerkunci);
    const ITEMS = @json((object) $items);
    const ERRORS = @json($errors->getMessages());

    const tbody = document.querySelector('#tabel-item tbody');
    const template = document.getElementById('template-baris');
    let indeks = 0;

    const hppUntuk = (barangId) => {
        if (HPP_TERKUNCI[barangId] !== undefined) return parseFloat(HPP_TERKUNCI[barangId]);
        return BARANG[barangId] ? BARANG[barangId].hpp : 0;
    };

    function tambahBaris(item = {}, key = null) {
        const i = key ?? indeks;
        indeks = Math.max(indeks, Number(i) + 1);

        const tr = template.content.firstElementChild.cloneNode(true);
        tr.querySelectorAll('[data-name]').forEach((el) => {
            el.name = `items[${i}][${el.dataset.name}]`;
            if (item[el.dataset.name] !== undefined && item[el.dataset.name] !== null) {
                el.value = el.type === 'number' && item[el.dataset.name] !== '' ? parseFloat(item[el.dataset.name]) : item[el.dataset.name];
            }
        });

        // Pesan error validasi server per baris
        tr.querySelectorAll('[data-err]').forEach((el) => {
            const pesan = ERRORS[`items.${i}.${el.dataset.err}`];
            if (pesan) {
                el.textContent = pesan[0];
                const input = tr.querySelector(`[data-name="${el.dataset.err}"]`);
                input?.classList.add('is-invalid', 'server-invalid');
            }
        });

        tbody.appendChild(tr);

        const selectBarang = tr.querySelector('.pilih-barang');
        new TomSelect(selectBarang, { placeholder: 'Cari barang...', allowEmptyOption: true, dropdownParent: 'body', closeAfterSelect: true });
        const selectSupplier = tr.querySelector('.pilih-supplier');
        // Supplier langganan barang otomatis dipilih (selama belum diubah manual)
        const isiSupplierOtomatis = () => {
            const b = BARANG[selectBarang.value];
            if (b && b.supplier_id && (selectSupplier.value === '' || selectSupplier.dataset.auto === '1')) {
                selectSupplier.value = b.supplier_id;
                selectSupplier.dataset.auto = '1';
                selectSupplier.classList.remove('is-invalid');
            }
        };
        selectSupplier.addEventListener('change', () => selectSupplier.dataset.auto = '0');

        selectBarang.addEventListener('change', () => {
            const b = BARANG[selectBarang.value];
            const jual = tr.querySelector('.input-jual');
            if (b && (jual.value === '' || jual.dataset.auto === '1')) {
                jual.value = b.harga_jual || '';
                jual.dataset.auto = '1';
            }
            isiSupplierOtomatis();
            perbaruiBaris(tr);
        });
        tr.querySelector('.input-jual').addEventListener('input', (e) => e.target.dataset.auto = '0');
        tr.querySelector('.pilih-sumber').addEventListener('change', () => {
            if (tr.querySelector('.pilih-sumber').value === 'supplier') {
                tr.querySelector('.input-beli').value = '';
                isiSupplierOtomatis();
            }
            perbaruiBaris(tr);
        });

        perbaruiBaris(tr);
        toggleKosong();
        return tr;
    }

    // Atur tampilan baris sesuai sumber (Supplier / Stok)
    function perbaruiBaris(tr) {
        const barangId = tr.querySelector('.pilih-barang').value;
        const b = BARANG[barangId];
        const stok = tr.querySelector('.pilih-sumber').value === 'stok';
        const beli = tr.querySelector('.input-beli');

        tr.classList.toggle('sumber-stok', stok);
        tr.classList.toggle('sumber-supplier', !stok);
        tr.querySelector('.badge-sumber').innerHTML = stok
            ? '<span class="badge badge-stok"><i class="bi bi-boxes me-1"></i>Stok</span>'
            : '<span class="badge badge-supplier"><i class="bi bi-truck me-1"></i>Supplier</span>';

        tr.querySelector('.kolom-supplier').classList.toggle('d-none', stok);
        tr.querySelector('.kolom-gudang').classList.toggle('d-none', !stok);
        tr.querySelector('.satuan').textContent = b ? b.satuan : '-';

        // Sumber stok: harga beli = HPP, dikunci (readonly + abu-abu)
        beli.readOnly = stok;
        beli.tabIndex = stok ? -1 : 0;
        beli.classList.toggle('input-locked', stok);
        tr.querySelector('.info-hpp').classList.toggle('d-none', !stok);
        if (stok) beli.value = b ? hppUntuk(barangId) : '';

        const infoStok = tr.querySelector('.info-stok');
        infoStok.classList.toggle('d-none', !stok || !b);
        if (stok && b) infoStok.textContent = `Sisa stok: ${formatQty(b.stok)} ${b.satuan}`;

        hitung();
    }

    function hitung() {
        let jual = 0, modal = 0, belanja = 0;
        const pemakaianStok = {};

        tbody.querySelectorAll('.baris-item').forEach((tr) => {
            const qty = parseFloat(tr.querySelector('.input-qty').value) || 0;
            const hBeli = parseFloat(tr.querySelector('.input-beli').value) || 0;
            const hJual = parseFloat(tr.querySelector('.input-jual').value) || 0;
            const subBeli = Math.round(qty * hBeli * 100) / 100;
            const subJual = Math.round(qty * hJual * 100) / 100;
            const stok = tr.querySelector('.pilih-sumber').value === 'stok';
            const barangId = tr.querySelector('.pilih-barang').value;

            jual += subJual;
            modal += subBeli;
            if (!stok) belanja += subBeli;
            if (stok && barangId) pemakaianStok[barangId] = (pemakaianStok[barangId] || 0) + qty;

            tr.querySelector('.sub-jual').textContent = formatRupiah(subJual);
            const laba = subJual - subBeli;
            const elLaba = tr.querySelector('.sub-laba');
            elLaba.textContent = `laba ${formatRupiah(laba)}`;
            elLaba.className = `sub-laba ${laba < 0 ? 'text-danger' : 'text-muted'}`;
        });

        // Tandai qty yang melebihi stok (dijumlah per barang)
        tbody.querySelectorAll('.baris-item').forEach((tr) => {
            const qtyInput = tr.querySelector('.input-qty');
            const info = tr.querySelector('.info-stok');
            const barangId = tr.querySelector('.pilih-barang').value;
            const stok = tr.querySelector('.pilih-sumber').value === 'stok';
            const lebih = stok && BARANG[barangId] && pemakaianStok[barangId] > BARANG[barangId].stok + 0.0001;
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

    document.querySelectorAll('[data-tambah]').forEach((btn) => btn.addEventListener('click', () => {
        const tr = tambahBaris({ sumber: btn.dataset.tambah });
        tr.querySelector('.pilih-barang').tomselect.focus();
    }));

    tbody.addEventListener('click', (e) => {
        if (!e.target.closest('.hapus-baris')) return;
        e.target.closest('tr').remove();
        toggleKosong();
        hitung();
    });
    tbody.addEventListener('input', hitung);

    // Cegah submit jika ada qty melebihi stok
    document.getElementById('form-nota').addEventListener('submit', (e) => {
        if (tbody.children.length === 0) {
            e.preventDefault();
            Toast.fire({ icon: 'warning', title: 'Tambahkan minimal 1 item.' });
            return;
        }
        if (tbody.querySelector('.info-stok.text-danger:not(.d-none)')) {
            e.preventDefault();
            Toast.fire({ icon: 'error', title: 'Ada qty yang melebihi sisa stok.' });
        }
    });

    // Muat baris awal (edit / old input), atau 1 baris kosong
    const keys = Object.keys(ITEMS);
    if (keys.length) {
        keys.forEach((k) => tambahBaris(ITEMS[k], k));
    } else {
        tambahBaris({ sumber: 'supplier' });
    }
</script>
@endpush
