@php
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
    $jml = fn ($v) => $excel ? round((float) $v, 2) : qty($v);
@endphp
<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Barang</th>
            <th>Satuan</th>
            <th class="text-end num">Stok Awal</th>
            <th class="text-end num">Masuk</th>
            <th class="text-end num">Keluar</th>
            <th class="text-end num">Penyesuaian</th>
            <th class="text-end num">Stok Akhir</th>
            <th class="text-end num">HPP</th>
            <th class="text-end num">Nilai Stok Akhir</th>
            <th class="text-end num">Nilai Penyesuaian</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r->nama }}</td>
                <td>{{ $r->satuan }}</td>
                <td class="num">{{ $jml($r->stok_awal) }}</td>
                <td class="num">{{ $jml($r->masuk) }}</td>
                <td class="num">{{ $jml($r->keluar) }}</td>
                <td class="num">{{ $jml($r->penyesuaian) }}</td>
                <td class="num"><strong>{{ $jml($r->stok_akhir) }}</strong></td>
                <td class="num">{{ $uang($r->hpp) }}</td>
                <td class="num">{{ $uang($r->nilai_akhir) }}</td>
                <td class="num">{{ $uang($r->nilai_penyesuaian) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="8">TOTAL</th>
            <th class="num">{{ $uang($total['nilai_akhir']) }}</th>
            <th class="num">{{ $uang($total['nilai_penyesuaian']) }}</th>
        </tr>
    </tfoot>
</table>
@unless ($excel)
    <p class="small text-muted px-3 py-2 mb-0">Nilai stok akhir memakai HPP rata-rata terkini. Nilai penyesuaian berasal dari stok opname (minus = kerugian/kehilangan barang).</p>
@endunless
