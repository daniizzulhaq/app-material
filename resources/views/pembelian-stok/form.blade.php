@extends('layouts.admin')

@php($edit = $pembelian->exists)

@section('title', $edit ? 'Edit Pembelian' : 'Pembelian Stok Baru')
@section('page-title', $edit ? "Edit Pembelian {$pembelian->no_faktur}" : 'Pembelian Stok Baru')
@section('breadcrumb')
    <a href="{{ route('pembelian-stok.index') }}">Pembelian Stok</a> / {{ $edit ? 'Edit' : 'Baru' }}
@endsection

@section('content')
<form method="POST" action="{{ $edit ? route('pembelian-stok.update', $pembelian) : route('pembelian-stok.store') }}" id="form-pembelian" novalidate>
    @csrf
    @if ($edit) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', $pembelian->tanggal?->toDateString()) }}"
                           class="form-control @error('tanggal') is-invalid @enderror" required>
                    @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select id="supplier_id" name="supplier_id" class="tom-select @error('supplier_id') is-invalid @enderror" placeholder="Pilih supplier..." required>
                        <option value="">Pilih supplier...</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(old('supplier_id', $pembelian->supplier_id) == $s->id)>{{ $s->nama }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">Metode Bayar <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group">
                        @foreach (['tunai' => 'Tunai', 'hutang' => 'Hutang'] as $val => $label)
                            <input type="radio" class="btn-check" name="metode_bayar" id="metode-{{ $val }}" value="{{ $val }}"
                                   @checked(old('metode_bayar', $pembelian->metode_bayar) === $val)>
                            <label class="btn btn-outline-primary" for="metode-{{ $val }}">{{ $label }}</label>
                        @endforeach
                    </div>
                    <div class="form-text">Tunai = cashflow keluar langsung. Hutang = keluar saat dibayar.</div>
                    @error('metode_bayar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="keterangan" class="form-label">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $pembelian->keterangan) }}" class="form-control" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title">Daftar Barang</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" id="tambah-baris"><i class="bi bi-plus-lg me-1"></i>Tambah Baris</button>
        </div>
        @error('items') <div class="alert alert-danger m-3 mb-0 py-2">{{ $message }}</div> @enderror
        <div class="table-responsive">
            <table class="table align-middle mb-0 tabel-item" id="tabel-item">
                <thead>
                    <tr>
                        <th style="min-width:260px">Barang</th>
                        <th class="text-end" style="width:150px">Qty</th>
                        <th class="text-end" style="width:200px">Harga Beli</th>
                        <th class="text-end" style="width:170px">Subtotal</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $i => $item)
                        <tr class="baris-item">
                            <td class="kolom-barang" data-label="Barang">
                                <select name="items[{{ $i }}][barang_id]" class="pilih-barang @error("items.$i.barang_id") is-invalid @enderror" placeholder="Cari barang...">
                                    <option value="">Cari barang...</option>
                                    @foreach ($barangs as $b)
                                        <option value="{{ $b->id }}" data-satuan="{{ $b->satuan }}" data-hpp="{{ (float) $b->hpp }}" data-beli="{{ (float) $b->harga_beli_default }}" @selected(($item['barang_id'] ?? null) == $b->id)>{{ $b->nama }}</option>
                                    @endforeach
                                </select>
                                @error("items.$i.barang_id") <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <small class="text-muted info-barang"></small>
                            </td>
                            <td data-label="Qty">
                                <div class="input-group input-group-sm has-validation">
                                    <input type="number" name="items[{{ $i }}][qty]" value="{{ filled($item['qty'] ?? null) ? (float) $item['qty'] : '' }}" min="0" step="any"
                                           class="form-control text-end input-qty @error("items.$i.qty") is-invalid @enderror">
                                    <span class="input-group-text satuan">-</span>
                                    @error("items.$i.qty") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </td>
                            <td data-label="Harga Beli">
                                <div class="input-group input-group-sm has-validation">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" name="items[{{ $i }}][harga_beli]" value="{{ filled($item['harga_beli'] ?? null) ? input_uang($item['harga_beli']) : '' }}" inputmode="numeric" autocomplete="off"
                                           class="form-control text-end input-uang input-harga @error("items.$i.harga_beli") is-invalid @enderror">
                                    @error("items.$i.harga_beli") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </td>
                            <td class="num fw-semibold subtotal kolom-penuh" data-label="Subtotal">Rp 0</td>
                            <td class="kolom-hapus"><button type="button" class="btn btn-sm btn-light text-danger hapus-baris" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total Pembelian</th>
                        <th class="num fs-6" id="grand-total">Rp 0</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="{{ $edit ? route('pembelian-stok.show', $pembelian) : route('pembelian-stok.index') }}" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Pembelian</button>
    </div>
</form>

{{-- Template baris baru --}}
<template id="template-baris">
    <tr class="baris-item">
        <td class="kolom-barang" data-label="Barang">
            <select data-name="barang_id" class="pilih-barang" placeholder="Cari barang...">
                <option value="">Cari barang...</option>
                @foreach ($barangs as $b)
                    <option value="{{ $b->id }}" data-satuan="{{ $b->satuan }}" data-hpp="{{ (float) $b->hpp }}" data-beli="{{ (float) $b->harga_beli_default }}">{{ $b->nama }}</option>
                @endforeach
            </select>
            <small class="text-muted info-barang"></small>
        </td>
        <td data-label="Qty">
            <div class="input-group input-group-sm">
                <input type="number" data-name="qty" min="0" step="any" class="form-control text-end input-qty">
                <span class="input-group-text satuan">-</span>
            </div>
        </td>
        <td data-label="Harga Beli">
            <div class="input-group input-group-sm">
                <span class="input-group-text">Rp</span>
                <input type="text" data-name="harga_beli" inputmode="numeric" autocomplete="off" class="form-control text-end input-uang input-harga">
            </div>
        </td>
        <td class="num fw-semibold subtotal kolom-penuh" data-label="Subtotal">Rp 0</td>
        <td class="kolom-hapus"><button type="button" class="btn btn-sm btn-light text-danger hapus-baris" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script type="module">
    const tbody = document.querySelector('#tabel-item tbody');
    let indeks = {{ count($items) ? max(array_keys($items)) + 1 : 0 }};

    function initBaris(tr) {
        const select = tr.querySelector('.pilih-barang');
        new TomSelect(select, { placeholder: 'Cari barang...', allowEmptyOption: true, dropdownParent: 'body', closeAfterSelect: true });

        const perbaruiInfo = () => {
            const opt = select.selectedOptions[0];
            const satuan = opt?.dataset.satuan || '-';
            tr.querySelector('.satuan').textContent = satuan;
            tr.querySelector('.info-barang').textContent = opt && opt.value ? `HPP saat ini: ${formatRupiah(opt.dataset.hpp)}` : '';
        };
        select.addEventListener('change', () => {
            // Harga beli otomatis dari data barang bila masih kosong
            const opt = select.selectedOptions[0];
            const harga = tr.querySelector('.input-harga');
            if (opt && opt.value && harga.value === '' && parseFloat(opt.dataset.beli) > 0) {
                harga.value = Math.round(parseFloat(opt.dataset.beli)).toLocaleString('id-ID');
                hitung();
            }
            perbaruiInfo();
        });
        perbaruiInfo();
    }

    function hitung() {
        let total = 0;
        tbody.querySelectorAll('.baris-item').forEach((tr) => {
            const qty = parseFloat(tr.querySelector('.input-qty').value) || 0;
            const harga = parseUang(tr.querySelector('.input-harga').value);
            const sub = Math.round(qty * harga * 100) / 100;
            tr.querySelector('.subtotal').textContent = formatRupiah(sub);
            total += sub;
        });
        document.getElementById('grand-total').textContent = formatRupiah(total);
    }

    document.getElementById('tambah-baris').addEventListener('click', () => {
        const tr = document.getElementById('template-baris').content.firstElementChild.cloneNode(true);
        tr.querySelectorAll('[data-name]').forEach((el) => el.name = `items[${indeks}][${el.dataset.name}]`);
        indeks++;
        tbody.appendChild(tr);
        initBaris(tr);
        tr.querySelector('.pilih-barang').tomselect.focus();
    });

    tbody.addEventListener('click', (e) => {
        if (!e.target.closest('.hapus-baris')) return;
        if (tbody.querySelectorAll('.baris-item').length === 1) {
            Toast.fire({ icon: 'info', title: 'Minimal harus ada 1 baris barang.' });
            return;
        }
        e.target.closest('tr').remove();
        hitung();
    });

    tbody.addEventListener('input', hitung);
    tbody.querySelectorAll('.baris-item').forEach(initBaris);
    hitung();
</script>
@endpush
