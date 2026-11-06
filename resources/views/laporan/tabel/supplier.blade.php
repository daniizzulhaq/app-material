@php
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
@endphp
<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Supplier</th>
            <th class="text-end num">Belanja Langsung (Nota)</th>
            <th class="text-end num">Pembelian Stok</th>
            <th class="text-end num">Total Belanja</th>
            <th class="text-end num">Dibayar</th>
            <th class="text-end num">Sisa Hutang</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r->nama }}</td>
                <td class="num">{{ $uang($r->belanja_langsung) }}</td>
                <td class="num">{{ $uang($r->pembelian_stok) }}</td>
                <td class="num">{{ $uang($r->total) }}</td>
                <td class="num">{{ $uang($r->dibayar) }}</td>
                <td class="num">{{ $uang($r->sisa) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th>TOTAL</th>
            <th class="num">{{ $uang($total['belanja_langsung']) }}</th>
            <th class="num">{{ $uang($total['pembelian_stok']) }}</th>
            <th class="num">{{ $uang($total['total']) }}</th>
            <th class="num">{{ $uang($total['dibayar']) }}</th>
            <th class="num">{{ $uang($total['sisa']) }}</th>
        </tr>
    </tfoot>
</table>
@unless ($excel)
    <p class="small text-muted px-3 py-2 mb-0">Belanja langsung dari nota dianggap dibayar pada hari yang sama (tercatat otomatis di cashflow "Belanja Supplier"). Sisa hutang berasal dari pembelian stok dengan metode hutang.</p>
@endunless
