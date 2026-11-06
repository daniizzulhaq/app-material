{{-- $excel = true → angka mentah agar bisa dihitung di Excel --}}
@php
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
@endphp
<table class="table table-hover align-middle">
    <thead>
        <tr>
            @if ($klien)
                <th>No. Nota</th>
                <th>Tanggal</th>
                <th>Status</th>
            @else
                <th>Klien</th>
                <th>Kategori</th>
                <th class="text-end num">Jumlah Nota</th>
            @endif
            <th class="text-end num">Total Jual</th>
            <th class="text-end num">Dibayar</th>
            <th class="text-end num">Sisa Piutang</th>
            <th class="text-end num">Laba</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                @if ($klien)
                    <td>{{ $r->no_nota }}</td>
                    <td>{{ tanggal($r->tanggal) }}</td>
                    <td>{{ ucfirst($r->status_bayar) }}</td>
                @else
                    <td>{{ $r->nama }}</td>
                    <td>{{ \App\Models\Klien::KATEGORI[$r->kategori]['label'] ?? '-' }}</td>
                    <td class="num">{{ $r->jumlah_nota }}</td>
                @endif
                <td class="num">{{ $uang($r->total_jual) }}</td>
                <td class="num">{{ $uang($r->dibayar) }}</td>
                <td class="num">{{ $uang($r->sisa) }}</td>
                <td class="num">{{ $uang($r->laba) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        @foreach ($totalKategori ?? [] as $val => $t)
            <tr>
                <th colspan="2">Total Klien {{ \App\Models\Klien::KATEGORI[$val]['label'] }}</th>
                <th class="num">{{ $t['jumlah_nota'] }}</th>
                <th class="num">{{ $uang($t['total_jual']) }}</th>
                <th class="num">{{ $uang($t['dibayar']) }}</th>
                <th class="num">{{ $uang($t['sisa']) }}</th>
                <th class="num">{{ $uang($t['laba']) }}</th>
            </tr>
        @endforeach
        <tr>
            <th colspan="{{ $klien ? 3 : 2 }}">TOTAL{{ $kategori ? ' KLIEN ' . strtoupper(\App\Models\Klien::KATEGORI[$kategori]['label']) : '' }}</th>
            @unless ($klien) <th class="num">{{ $total['jumlah_nota'] }}</th> @endunless
            <th class="num">{{ $uang($total['total_jual']) }}</th>
            <th class="num">{{ $uang($total['dibayar']) }}</th>
            <th class="num">{{ $uang($total['sisa']) }}</th>
            <th class="num">{{ $uang($total['laba']) }}</th>
        </tr>
    </tfoot>
</table>
