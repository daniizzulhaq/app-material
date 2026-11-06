<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota {{ $transaksi->no_nota }}</title>
    {{-- CSS inline & layout tabel agar kompatibel dengan DomPDF --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: {{ $pdf ? "'DejaVu Sans', sans-serif" : "'Inter', 'Segoe UI', Arial, sans-serif" }}; font-size: 11px; color: #222; margin: 0; }
        .halaman { padding: {{ $pdf ? '0' : '24px' }}; max-width: 820px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: top; }
        .nama-toko { font-size: 18px; font-weight: bold; color: #1e1f2e; }
        .judul { font-size: 16px; font-weight: bold; letter-spacing: 2px; color: #4f6ef7; text-align: right; }
        .muted { color: #777; }
        .items { margin-top: 14px; }
        .items th { background: #1e1f2e; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .items td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .total td { padding: 4px 8px; }
        .total .label { text-align: right; color: #555; }
        .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #1e1f2e; }
        .ttd td { padding-top: 30px; text-align: center; width: 50%; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 10px; }
        .status-lunas { background: #e9f9ef; color: #15803d; }
        .status-sebagian { background: #fef5e6; color: #b45309; }
        .status-belum { background: #fdecec; color: #b91c1c; }
        .aksi { text-align: center; margin: 16px 0; }
        .aksi button { padding: 8px 16px; border: 0; border-radius: 8px; background: #4f6ef7; color: #fff; cursor: pointer; font-size: 13px; }
        @media print { .aksi { display: none; } .halaman { padding: 0; } }
    </style>
</head>
<body>
<div class="halaman">
    @unless ($pdf)
        <div class="aksi"><button onclick="window.print()">🖨 Cetak Nota</button></div>
    @endunless

    <table class="kop">
        <tr>
            <td>
                <div class="nama-toko">{{ config('app.name') }}</div>
                <div class="muted">Toko Bahan Bangunan &amp; Material</div>
            </td>
            <td class="judul">NOTA PENJUALAN</td>
        </tr>
    </table>

    <table style="margin-top: 12px">
        <tr>
            <td style="width: 55%; vertical-align: top">
                <div class="muted">Kepada:</div>
                <strong>{{ $transaksi->klien->nama }}</strong><br>
                @if ($transaksi->klien->alamat) {{ $transaksi->klien->alamat }}<br> @endif
                @if ($transaksi->klien->no_hp) {{ $transaksi->klien->no_hp }} @endif
            </td>
            <td style="vertical-align: top">
                <table>
                    <tr><td class="muted">No. Nota</td><td><strong>{{ $transaksi->no_nota }}</strong></td></tr>
                    <tr><td class="muted">Tanggal</td><td>{{ tanggal($transaksi->tanggal) }}</td></tr>
                    <tr><td class="muted">Status</td><td><span class="status status-{{ $transaksi->status_bayar }}">{{ strtoupper($transaksi->status_bayar) }}</span></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px">No</th>
                <th>Barang</th>
                <th class="num">Qty</th>
                <th class="num">Harga</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transaksi->details as $d)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $d->barang->nama }}</td>
                    <td class="num">{{ qty($d->qty) }} {{ $d->barang->satuan }}</td>
                    <td class="num">{{ rupiah($d->harga_jual) }}</td>
                    <td class="num">{{ rupiah($d->subtotal_jual) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $dibayar = (float) ($transaksi->pembayarans_sum_jumlah ?? 0);
    @endphp
    <table class="total" style="margin-top: 8px">
        <tr class="grand"><td class="label" style="width: 75%">TOTAL</td><td class="num">{{ rupiah($transaksi->total_jual) }}</td></tr>
        <tr><td class="label">Dibayar</td><td class="num">{{ rupiah($dibayar) }}</td></tr>
        <tr><td class="label"><strong>Sisa</strong></td><td class="num"><strong>{{ rupiah(max(0, $transaksi->total_jual - $dibayar)) }}</strong></td></tr>
    </table>

    @if ($transaksi->keterangan)
        <p class="muted" style="margin-top: 10px">Keterangan: {{ $transaksi->keterangan }}</p>
    @endif

    <table class="ttd">
        <tr>
            <td>Penerima,<br><br><br><br>( ____________________ )</td>
            <td>Hormat kami,<br><br><br><br>( ____________________ )</td>
        </tr>
    </table>
</div>
</body>
</html>
