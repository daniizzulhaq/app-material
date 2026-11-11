{{--
    Laporan per supplier: daftar barang dari tiap supplier (rincian, seperti web sayur) lalu jumlah keseluruhan.
    Belanja nota ke supplier lunas otomatis per hari; sisa hutang hanya dari pembelian stok metode hutang.
--}}
@php
    $html = ! $excel && ! ($pdf ?? false);
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
@endphp

@include('laporan._rincian', [
    'judulRincian' => 'Barang dari',
    'kolomKlien' => $opsi->tampilKlien,
    'kolomSumber' => false,
])

@if ($rincian->isNotEmpty())
    @if ($excel)
        <table><tr><td><strong>Jumlah Keseluruhan</strong></td></tr></table>
    @else
        <div class="judul-rincian {{ $html ? 'px-3 pt-3 pb-2 fw-bold border-top' : '' }}">Jumlah Keseluruhan</div>
    @endif
@endif

<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Supplier</th>
            <th class="text-end num">Jumlah Nota</th>
            <th class="text-end num">Total Qty</th>
            @if ($opsi->beli()) <th class="text-end num">Total Beli (Nota)</th> @endif
            @if ($opsi->jual()) <th class="text-end num">Total Jual</th> @endif
            @if ($opsi->laba()) <th class="text-end num">Laba</th> @endif
            @if ($opsi->beli())
                <th class="text-end num">Pembelian Stok</th>
                <th class="text-end num">Sisa Hutang Stok</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>
                    @if ($html)
                        <a href="{{ request()->fullUrlWithQuery(['supplier_id' => [$r->id], 'export' => null, 'unduh' => null]) }}" class="text-decoration-none" title="Lihat barang supplier ini saja">{{ $r->nama }}</a>
                    @else
                        {{ $r->nama }}
                    @endif
                </td>
                <td class="num">{{ $r->nota }}</td>
                <td class="num">{{ $opsi->qty($r->qty, null, $excel) }}</td>
                @if ($opsi->beli()) <td class="num">{{ $uang($r->total_beli) }}</td> @endif
                @if ($opsi->jual()) <td class="num">{{ $uang($r->total_jual) }}</td> @endif
                @if ($opsi->laba()) <td class="num {{ $html ? ($r->laba < 0 ? 'text-danger' : 'text-success') : '' }}">{{ $uang($r->laba) }}</td> @endif
                @if ($opsi->beli())
                    <td class="num">{{ $uang($r->pembelian_stok) }}</td>
                    <td class="num {{ $html && $r->sisa_hutang > 0 ? 'text-danger' : '' }}">{{ $uang($r->sisa_hutang) }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th>TOTAL</th>
            <th class="num">{{ $total['nota'] }}</th>
            <th class="num">{{ $opsi->qty($total['qty'], null, $excel) }}</th>
            @if ($opsi->beli()) <th class="num">{{ $uang($total['total_beli']) }}</th> @endif
            @if ($opsi->jual()) <th class="num">{{ $uang($total['total_jual']) }}</th> @endif
            @if ($opsi->laba()) <th class="num">{{ $uang($total['laba']) }}</th> @endif
            @if ($opsi->beli())
                <th class="num">{{ $uang($total['pembelian_stok']) }}</th>
                <th class="num">{{ $uang($total['sisa_hutang']) }}</th>
            @endif
        </tr>
    </tfoot>
</table>
@if ($html)
    <p class="small text-muted px-3 py-2 mb-0"><i class="bi bi-info-circle me-1"></i>Daftar barang = item nota dari supplier (lunas otomatis per hari). Klik nama supplier untuk melihat satu supplier saja.
        Total qty menjumlahkan semua satuan. Pembelian stok tunai langsung lunas; metode hutang dibayar dari halaman Pembelian Stok.
        @if (! empty($filterKlien)) <br>Filter klien aktif: pembelian stok tidak dihitung (tidak terkait klien). @endif</p>
@endif
