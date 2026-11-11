@extends('layouts.admin')

@php
    $edit = $pembayaran->exists;
    $hutang = $pembayaran->jenis === 'supplier'; // pelunasan hutang pembelian stok
    $judul = $hutang ? ($edit ? 'Edit Pembayaran Hutang' : 'Bayar Hutang Pembelian') : ($edit ? 'Edit Pembayaran Klien' : 'Terima Pembayaran Klien');
    $batal = $hutang && $pembayaran->pembelian_stok_id ? route('pembelian-stok.show', $pembayaran->pembelian_stok_id) : route('pembayaran.index');
@endphp

@section('title', $judul)
@section('page-title', $judul)
@section('breadcrumb')
    @if ($hutang)
        <a href="{{ route('pembelian-stok.index') }}">Pembelian Stok</a> / Bayar Hutang
    @else
        <a href="{{ route('pembayaran.index') }}">Pembayaran</a> / {{ $edit ? 'Edit' : 'Baru' }}
    @endif
@endsection

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $edit ? route('pembayaran.update', $pembayaran) : route('pembayaran.store') }}" novalidate>
                    @csrf
                    @if ($edit) @method('PUT') @endif
                    <input type="hidden" name="jenis" value="{{ $hutang ? 'supplier' : 'klien' }}">

                    @if ($hutang)
                        <input type="hidden" name="pembelian_stok_id" value="{{ $pembayaran->pembelian_stok_id }}">
                        <div class="alert alert-light border small mb-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-receipt me-1"></i>{{ $pembelian?->no_faktur }} · {{ $pembelian?->supplier->nama }}</div>
                            Total {{ rupiah($pembelian?->total) }} · Sisa hutang <strong class="text-danger">{{ rupiah($sisaHutang) }}</strong>
                            <button type="button" class="btn btn-sm btn-link p-0 ms-2 isi-lunas" data-sisa="{{ $sisaHutang }}">Isi lunas</button>
                        </div>
                        @error('pembelian_stok_id') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                    @else
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="klien_id" class="form-label">Klien <span class="text-danger">*</span></label>
                                <select id="klien_id" name="klien_id" class="tom-select @error('klien_id') is-invalid @enderror" placeholder="Pilih klien...">
                                    <option value="">Pilih klien...</option>
                                    @foreach ($kliens as $k)
                                        <option value="{{ $k->id }}" @selected((string) old('klien_id', $pembayaran->klien_id) === (string) $k->id)>{{ $k->nama }} · {{ $k->nama_kategori }}</option>
                                    @endforeach
                                </select>
                                @error('klien_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="transaksi_id" class="form-label">Kaitkan ke Nota <span class="text-muted fw-normal small">(per nota)</span></label>
                                <select id="transaksi_id" name="transaksi_id" class="@error('transaksi_id') is-invalid @enderror"
                                        data-selected="{{ old('transaksi_id', $pembayaran->transaksi_id) }}">
                                    <option value="">Tanpa nota</option>
                                </select>
                                @error('transaksi_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <div class="form-text" id="info-nota">Pilih klien untuk melihat nota yang belum lunas.</div>
                            </div>
                        </div>

                        <div class="alert alert-light border small d-none" id="info-tagihan">
                            Total nota <strong id="info-total"></strong> · Sisa <strong class="text-danger" id="info-sisa"></strong>
                            <button type="button" class="btn btn-sm btn-link p-0 ms-2 isi-lunas" id="isi-lunas-nota">Isi lunas</button>
                        </div>
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', $pembayaran->tanggal?->toDateString()) }}"
                                   class="form-control @error('tanggal') is-invalid @enderror" required>
                            @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="jumlah" class="form-label">Jumlah <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="jumlah" name="jumlah" inputmode="numeric" autocomplete="off" value="{{ old('jumlah', $edit ? input_uang($pembayaran->jumlah) : '') }}"
                                       class="form-control text-end input-uang @error('jumlah') is-invalid @enderror" placeholder="0" required>
                                @error('jumlah') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="metode" class="form-label">Metode <span class="text-danger">*</span></label>
                            <select id="metode" name="metode" class="form-select @error('metode') is-invalid @enderror">
                                <option value="tunai" @selected(old('metode', $pembayaran->metode) === 'tunai')>Tunai</option>
                                <option value="transfer" @selected(old('metode', $pembayaran->metode) === 'transfer')>Transfer</option>
                            </select>
                            @error('metode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $pembayaran->keterangan) }}" class="form-control" placeholder="Opsional, mis. transfer BCA">
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ $batal }}" class="btn btn-light">Batal</a>
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
    const elJumlah = document.getElementById('jumlah');
    const isiJumlah = (n) => { elJumlah.value = Math.round(Math.max(0, n)).toLocaleString('id-ID'); };

    // Bayar hutang pembelian stok: hanya tombol isi lunas
    document.querySelectorAll('.isi-lunas[data-sisa]').forEach((b) => b.addEventListener('click', () => isiJumlah(parseFloat(b.dataset.sisa))));

    const elKlien = document.getElementById('klien_id');
    const elNota = document.getElementById('transaksi_id');
    if (elKlien && elNota) {
        const URL_NOTA = @json(route('pembayaran.nota'));
        const ID_PEMBAYARAN = @json($pembayaran->id);
        const info = document.getElementById('info-nota');
        let terpilih = elNota.dataset.selected || '';
        let dataNota = {};
        let permintaan = 0;
        let sisaAktif = 0;

        const tsNota = new TomSelect(elNota, {
            dropdownParent: 'body',
            allowEmptyOption: true,
            placeholder: 'Tanpa nota',
            maxOptions: 1000,
            render: { no_results: () => '<div class="no-results">Tidak ada nota</div>' },
        });

        function tampilInfo() {
            const n = dataNota[tsNota.getValue()];
            const box = document.getElementById('info-tagihan');
            box.classList.toggle('d-none', !n);
            if (!n) return;
            sisaAktif = n.sisa;
            document.getElementById('info-total').textContent = formatRupiah(n.total);
            document.getElementById('info-sisa').textContent = formatRupiah(n.sisa);
        }

        /** Muat nota klien terpilih yang belum lunas (+ nota yang sedang dipilih saat edit). */
        async function muatNota() {
            const nomor = ++permintaan;
            tsNota.clear(true);
            tsNota.clearOptions();
            tsNota.addOption({ value: '', text: 'Tanpa nota' });
            dataNota = {};
            tampilInfo();

            if (!elKlien.value) {
                info.textContent = 'Pilih klien untuk melihat nota yang belum lunas.';
                return;
            }

            info.textContent = 'Memuat nota...';
            const params = new URLSearchParams({ klien_id: elKlien.value, selected: terpilih, pembayaran: ID_PEMBAYARAN ?? '' });
            let data;
            try {
                const res = await fetch(`${URL_NOTA}?${params}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error('gagal');
                data = await res.json();
            } catch (e) {
                if (nomor === permintaan) info.textContent = 'Daftar nota gagal dimuat. Pilih ulang klien untuk mencoba lagi.';
                return;
            }
            if (nomor !== permintaan) return;

            data.forEach((n) => { tsNota.addOption(n); dataNota[n.value] = n; });
            tsNota.refreshOptions(false);
            info.textContent = data.length
                ? `${data.length} nota belum lunas. Status nota diperbarui otomatis; jumlah terisi sesuai sisa.`
                : 'Klien ini tidak punya nota yang belum lunas.';

            if (terpilih && dataNota[terpilih]) tsNota.setValue(terpilih, true);
            terpilih = '';
            tampilInfo();
        }

        tsNota.on('change', (val) => {
            const n = dataNota[val];
            if (n && !elJumlah.value) isiJumlah(n.sisa);
            tampilInfo();
        });
        elKlien.addEventListener('change', muatNota);
        document.getElementById('isi-lunas-nota').addEventListener('click', () => isiJumlah(sisaAktif));

        muatNota();
    }
</script>
@endpush
