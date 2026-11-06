@php
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
    $jml = fn ($v) => $excel ? round((float) $v, 2) : qty($v);
@endphp
<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Barang</th>
            <th>Satuan</th>
            <th class="text-end num">Qty dari Supplier</th>
            <th class="text-end num">Qty dari Stok</th>
            <th class="text-end num">Total Qty</th>
            <th class="text-end num">Total Modal</th>
            <th class="text-end num">Total Jual</th>
            <th class="text-end num">Laba</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r->nama }}</td>
                <td>{{ $r->satuan }}</td>
                <td class="num">{{ $jml($r->qty_supplier) }}</td>
                <td class="num">{{ $jml($r->qty_stok) }}</td>
                <td class="num">{{ $jml($r->qty_total) }}</td>
                <td class="num">{{ $uang($r->total_modal) }}</td>
                <td class="num">{{ $uang($r->total_jual) }}</td>
                <td class="num">{{ $uang($r->laba) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="5">TOTAL</th>
            <th class="num">{{ $uang($total['total_modal']) }}</th>
            <th class="num">{{ $uang($total['total_jual']) }}</th>
            <th class="num">{{ $uang($total['laba']) }}</th>
        </tr>
    </tfoot>
</table>
