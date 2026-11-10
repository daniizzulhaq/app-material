@extends('layouts.admin')

@php($edit = $pembayaran->exists)
@php($tagihanTerpilih = old('tagihan', $pembayaran->tanggal_nota ? 'tanggal:' . $pembayaran->tanggal_nota->toDateString() : ($pembayaran->pembelian_stok_id ? 'faktur:' . $pembayaran->pembelian_stok_id : '')))

@section('title', $edit ? 'Edit Pembayaran' : 'Catat Pembayaran')
@section('page-title', $edit ? 'Edit Pembayaran' : 'Catat Pembayaran')
@section('breadcrumb')
    <a href="{{ route('pembayaran.index') }}">Pembayaran</a> / {{ $edit ? 'Edit' : 'Baru' }}
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $edit ? route('pembayaran.update', $pembayaran) : route('pembayaran.store') }}" novalidate>
                    @csrf
                    @if ($edit) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label d-block">Jenis Pembayaran <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="jenis" id="jenis-klien" value="klien" @checked(old('jenis', $pembayaran->jenis) === 'klien')>
                            <label class="btn btn-outline-success" for="jenis-klien"><i class="bi bi-arrow-down-circle me-1"></i>Dari Klien (masuk)</label>
                            <input type="radio" class="btn-check" name="jenis" id="jenis-supplier" value="supplier" @checked(old('jenis', $pembayaran->jenis) === 'supplier')>
                            <label class="btn btn-outline-danger" for="jenis-supplier"><i class="bi bi-arrow-up-circle me-1"></i>Ke Supplier (keluar)</label>
                        </div>
                    </div>

                    <div class="mb-3 grup-jenis" data-jenis="klien">
                        <label for="transaksi_id" class="form-label">Nota yang Dibayar <span class="text-danger">*</span></label>
                        <select id="transaksi_id" name="transaksi_id" class="tom-select pilih-tagihan @error('transaksi_id') is-invalid @enderror" placeholder="Cari no. nota / klien...">
                            <option value="">Cari no. nota / klien...</option>
                            @foreach ($notas as $n)
                                <option value="{{ $n['id'] }}" data-total="{{ $n['total'] }}" data-sisa="{{ $n['sisa'] }}" @selected(old('transaksi_id', $pembayaran->transaksi_id) == $n['id'])>
                                    {{ $n['label'] }} · sisa {{ rupiah($n['sisa']) }}
                                </option>
                            @endforeach
                        </select>
                        @error('transaksi_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @if ($notas->isEmpty()) <div class="form-text">Tidak ada nota yang belum lunas.</div> @endif
                    </div>

                    <div class="grup-jenis" data-jenis="supplier">
                        <div class="mb-3">
                            <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select id="supplier_id" name="supplier_id" class="tom-select @error('supplier_id') is-invalid @enderror" placeholder="Pilih supplier...">
                                <option value="">Pilih supplier...</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(old('supplier_id', $pembayaran->supplier_id) == $s->id)>{{ $s->nama }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="tagihan" class="form-label">Nota / Faktur yang Dibayar <span class="text-danger">*</span></label>
                            <select id="tagihan" name="tagihan" class="@error('tagihan') is-invalid @enderror" data-selected="{{ $tagihanTerpilih }}"></select>
                            @error('tagihan') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('pembelian_stok_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('tanggal_nota') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text" id="info-tagihan-supplier">Pilih supplier untuk melihat nominal nota per hari.</div>
                        </div>
                    </div>

                    <div class="alert alert-light border small d-none" id="info-tagihan">
                        Total tagihan <strong id="info-total"></strong> · Sisa <strong class="text-danger" id="info-sisa"></strong>
                        <button type="button" class="btn btn-sm btn-link p-0 ms-2" id="isi-lunas">Isi lunas</button>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', $pembayaran->tanggal?->toDateString()) }}"
                                   class="form-control @error('tanggal') is-invalid @enderror" required>
                            @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="metode" class="form-label">Metode <span class="text-danger">*</span></label>
                            <select id="metode" name="metode" class="form-select @error('metode') is-invalid @enderror">
                                <option value="tunai" @selected(old('metode', $pembayaran->metode) === 'tunai')>Tunai</option>
                                <option value="transfer" @selected(old('metode', $pembayaran->metode) === 'transfer')>Transfer</option>
                            </select>
                            @error('metode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="jumlah" class="form-label">Jumlah <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">Rp</span>
                            <input type="text" id="jumlah" name="jumlah" inputmode="numeric" autocomplete="off" value="{{ old('jumlah', $edit ? input_uang($pembayaran->jumlah) : '') }}"
                                   class="form-control text-end input-uang @error('jumlah') is-invalid @enderror" placeholder="0" required>
                            @error('jumlah') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $pembayaran->keterangan) }}" class="form-control" placeholder="Opsional">
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('pembayaran.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
    const URL_TAGIHAN = @json(route('pembayaran.tagihan-supplier'));
    const ID_PEMBAYARAN = @json($pembayaran->id);
    const elJumlah = document.getElementById('jumlah');
    const elSupplier = document.getElementById('supplier_id');
    const elTagihan = document.getElementById('tagihan');
    const infoSupplier = document.getElementById('info-tagihan-supplier');
    let terpilih = elTagihan.dataset.selected || '';
    let dataTagihan = {};
    let permintaan = 0;
    let sisaAktif = 0;

    const tsTagihan = new TomSelect(elTagihan, {
        dropdownParent: 'body',
        allowEmptyOption: true,
        placeholder: 'Pilih nota / faktur...',
        maxOptions: 1000,
        optgroups: [
            { value: 'nota', label: 'Nota per tanggal (semua klien digabung)' },
            { value: 'faktur', label: 'Faktur pembelian stok (hutang)' },
        ],
        lockOptgroupOrder: true,
        render: { no_results: () => '<div class="no-results">Tidak ada tagihan</div>' },
    });

    const jenis = () => document.querySelector('input[name="jenis"]:checked')?.value;

    /** Info total & sisa tagihan terpilih (nota klien / tagihan supplier) */
    function tampilInfo(total, sisa) {
        const info = document.getElementById('info-tagihan');
        if (total === null) { info.classList.add('d-none'); return; }
        sisaAktif = sisa;
        document.getElementById('info-total').textContent = formatRupiah(total);
        document.getElementById('info-sisa').textContent = formatRupiah(sisa);
        info.classList.remove('d-none');
    }

    function perbarui() {
        document.querySelectorAll('.grup-jenis').forEach((g) => g.classList.toggle('d-none', g.dataset.jenis !== jenis()));
        if (jenis() === 'klien') {
            const opt = document.getElementById('transaksi_id').selectedOptions[0];
            const ada = opt && opt.value;
            tampilInfo(ada ? parseFloat(opt.dataset.total) : null, ada ? parseFloat(opt.dataset.sisa) || 0 : 0);
        } else {
            const t = dataTagihan[tsTagihan.getValue()];
            tampilInfo(t ? t.total : null, t ? t.sisa : 0);
        }
    }

    /** Muat nominal nota per hari + faktur hutang milik supplier terpilih */
    async function muatTagihan() {
        const nomor = ++permintaan;
        tsTagihan.clear(true);
        tsTagihan.clearOptions();
        dataTagihan = {};
        perbarui();

        if (!elSupplier.value) {
            infoSupplier.textContent = 'Pilih supplier untuk melihat nominal nota per hari.';
            return;
        }

        infoSupplier.textContent = 'Memuat tagihan...';
        const params = new URLSearchParams({ supplier_id: elSupplier.value, pembayaran: ID_PEMBAYARAN ?? '', selected: terpilih });
        let data;
        try {
            const res = await fetch(`${URL_TAGIHAN}?${params}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('gagal');
            data = await res.json();
        } catch (e) {
            if (nomor === permintaan) infoSupplier.textContent = 'Tagihan gagal dimuat. Pilih ulang supplier untuk mencoba lagi.';
            return;
        }
        if (nomor !== permintaan) return;

        data.forEach((t) => { tsTagihan.addOption(t); dataTagihan[t.value] = t; });
        tsTagihan.refreshOptions(false);
        infoSupplier.textContent = data.length
            ? 'Nominal nota = total belanja ke supplier ini dari semua nota pada tanggal tsb. Jumlah terisi otomatis sesuai sisa.'
            : 'Supplier ini tidak punya tagihan yang belum lunas.';

        if (terpilih && dataTagihan[terpilih]) tsTagihan.setValue(terpilih, true);
        terpilih = '';
        perbarui();
    }

    // Pilih tagihan supplier -> jumlah otomatis = sisa (bila masih kosong)
    tsTagihan.on('change', (val) => {
        const t = dataTagihan[val];
        if (t && !elJumlah.value) elJumlah.value = Math.round(t.sisa).toLocaleString('id-ID');
        perbarui();
    });

    document.querySelectorAll('input[name="jenis"]').forEach((r) => r.addEventListener('change', perbarui));
    document.getElementById('transaksi_id').addEventListener('change', perbarui);
    elSupplier.addEventListener('change', muatTagihan);
    document.getElementById('isi-lunas').addEventListener('click', () => {
        elJumlah.value = Math.round(sisaAktif).toLocaleString('id-ID');
    });

    perbarui();
    muatTagihan();
</script>
@endpush
