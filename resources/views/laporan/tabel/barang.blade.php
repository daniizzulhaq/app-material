@php
    $html = ! $excel && ! ($pdf ?? false);
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
    $jml = fn ($v) => $excel ? round((float) $v, 2) : qty($v);
    $kiri = 6 + ($opsi->tampilKlien ? 1 : 0);
@endphp
<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Barang</th>
            @if ($opsi->tampilKlien) <th>Klien</th> @endif
            <th>Satuan</th>
            <th class="text-end num">Jumlah Nota</th>
            <th class="text-end num">Qty dari Supplier</th>
            <th class="text-end num">Qty dari Stok</th>
            <th class="text-end num">Total Qty</th>
            @if ($opsi->beli()) <th class="text-end num">Total Beli</th> @endif
            @if ($opsi->jual()) <th class="text-end num">Total Jual</th> @endif
            @if ($opsi->laba()) <th class="text-end num">Laba</th> @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r->nama }}</td>
                @if ($opsi->tampilKlien) <td>{{ $r->klien }}</td> @endif
                <td>{{ $r->satuan }}</td>
                <td class="num">{{ $r->nota }}</td>
                <td class="num">{{ $jml($r->qty_supplier) }}</td>
                <td class="num">{{ $jml($r->qty_stok) }}</td>
                <td class="num">{{ $jml($r->qty) }}</td>
                @if ($opsi->beli()) <td class="num">{{ $uang($r->total_beli) }}</td> @endif
                @if ($opsi->jual()) <td class="num">{{ $uang($r->total_jual) }}</td> @endif
                @if ($opsi->laba()) <td class="num {{ $html ? ($r->laba < 0 ? 'text-danger' : 'text-success') : '' }}">{{ $uang($r->laba) }}</td> @endif
            </tr>
        @endforeach
    </tbody>
    @if ($opsi->adaHarga())
        <tfoot>
            <tr>
                <th colspan="{{ $kiri }}">TOTAL</th>
                @if ($opsi->beli()) <th class="num">{{ $uang($total['total_beli']) }}</th> @endif
                @if ($opsi->jual()) <th class="num">{{ $uang($total['total_jual']) }}</th> @endif
                @if ($opsi->laba()) <th class="num">{{ $uang($total['laba']) }}</th> @endif
            </tr>
        </tfoot>
    @endif
</table>
@if ($html && $opsi->tampilKlien)
    <p class="small text-muted px-3 py-2 mb-0"><i class="bi bi-info-circle me-1"></i>Nama klien ditampilkan: satu baris per barang per klien. Pilih "Sembunyikan" untuk menggabung semua klien.</p>
@endif
