{{--
    Rincian barang per kelompok (klien / supplier), seperti web sayur. Dipakai halaman, PDF & Excel.
    Variabel: $rincian (koleksi {nama, items}), $opsi, $excel, $judulRincian,
              $kolomKlien (bool: kolom nama klien), $kolomSumber (bool: kolom supplier/stok)
--}}
@php
    $html = ! $excel && ! ($pdf ?? false);
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
    // Baris gabungan dengan harga berbeda: "Rp 60.000 / Rp 70.000"
    $harga = fn ($v, $list) => count($list ?? []) > 1
        ? implode(' / ', array_map(fn ($h) => $excel ? $h : rupiah($h), $list))
        : $uang($v);
    $kiri = 4 + ($kolomKlien ? 1 : 0) + ($kolomSumber ? 1 : 0) + ($excel ? 1 : 0); // kolom sebelum harga
    $kolomTotal = $kiri + ($opsi->beli() ? 2 : 0) + ($opsi->jual() ? 2 : 0) + ($opsi->laba() ? 1 : 0);
@endphp

@foreach ($rincian as $grup)
    @if ($excel)
        <table><tr><td colspan="{{ $kolomTotal }}"><strong>{{ $judulRincian }} {{ $grup->nama }}</strong></td></tr></table>
    @else
        <div class="judul-rincian {{ $html ? 'px-3 pt-3 pb-2 fw-bold' : '' }}">{{ $judulRincian }} {{ $grup->nama }}</div>
    @endif

    <table class="table table-hover align-middle {{ $html ? 'mb-0' : '' }} tabel-rincian">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                @if ($kolomKlien) <th>Klien</th> @endif
                @if ($kolomSumber) <th>Supplier / Sumber</th> @endif
                <th>Barang</th>
                <th class="text-end num">Qty</th>
                @if ($excel) <th>Satuan</th> @endif
                @if ($opsi->beli()) <th class="text-end num">Harga Beli</th><th class="text-end num">Subtotal Beli</th> @endif
                @if ($opsi->jual()) <th class="text-end num">Harga Jual</th><th class="text-end num">Subtotal Jual</th> @endif
                @if ($opsi->laba()) <th class="text-end num">Laba</th> @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($grup->items as $d)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="text-nowrap">
                        @if ($html)
                            <a href="{{ route('transaksi.show', $d->transaksi_id) }}" class="text-decoration-none">{{ tanggal($d->tanggal) }}</a>
                        @else
                            {{ tanggal($d->tanggal) }}
                        @endif
                    </td>
                    @if ($kolomKlien) <td>{{ $d->transaksi->klien->nama ?? '-' }}</td> @endif
                    @if ($kolomSumber) <td>{{ $d->sumber === 'stok' ? 'Stok gudang' : ($d->supplier->nama ?? '-') }}</td> @endif
                    <td>{{ $d->barang->nama ?? '-' }}</td>
                    <td class="num">{{ $opsi->qty($d->qty, $d->barang->satuan ?? null, $excel, $d->pecahan ?? []) }}</td>
                    @if ($excel) <td>{{ $d->barang->satuan ?? '' }}</td> @endif
                    @if ($opsi->beli())
                        <td class="num">{{ $harga($d->harga_beli, $d->harga_beli_list) }}</td>
                        <td class="num">{{ $uang($d->subtotal_beli) }}</td>
                    @endif
                    @if ($opsi->jual())
                        <td class="num">{{ $harga($d->harga_jual, $d->harga_jual_list) }}</td>
                        <td class="num">{{ $uang($d->subtotal_jual) }}</td>
                    @endif
                    @if ($opsi->laba())
                        @php $laba = $d->subtotal_jual - $d->subtotal_beli; @endphp
                        <td class="num {{ $html ? ($laba < 0 ? 'text-danger' : 'text-success') : '' }}">{{ $uang($laba) }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $kolomTotal }}" class="text-muted">Tidak ada barang pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if ($grup->items->isNotEmpty() && $opsi->adaHarga())
            <tfoot>
                <tr>
                    <th colspan="{{ $kiri }}">TOTAL</th>
                    @if ($opsi->beli()) <th></th><th class="num">{{ $uang($grup->items->sum('subtotal_beli')) }}</th> @endif
                    @if ($opsi->jual()) <th></th><th class="num">{{ $uang($grup->items->sum('subtotal_jual')) }}</th> @endif
                    @if ($opsi->laba()) <th class="num">{{ $uang($grup->items->sum('subtotal_jual') - $grup->items->sum('subtotal_beli')) }}</th> @endif
                </tr>
            </tfoot>
        @endif
    </table>
    @if ($excel)
        <table><tr><td></td></tr></table>
    @elseif (! $html)
        <div style="height: 14px"></div>
    @endif
@endforeach
