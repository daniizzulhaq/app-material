<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0; color: #1e1f2e; }
        .sub { color: #666; margin: 2px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e1f2e; color: #fff; padding: 5px 6px; text-align: left; font-size: 8px; text-transform: uppercase; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        tfoot th { background: #eef1fe; color: #1e1f2e; }
        .num, .text-end { text-align: right; white-space: nowrap; }
        p.small { color: #666; font-size: 8px; margin-top: 8px; }
        .footer { margin-top: 10px; color: #999; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $judul }}</h1>
    <div class="sub">
        {{ config('app.name') }} · Periode {{ tanggal($dari) }} – {{ tanggal($sampai) }}
        @isset($klien) @if ($klien) · Klien: {{ $klien->nama }} @endif @endisset
        @if (! empty($kategori)) · Kategori: Klien {{ \App\Models\Klien::KATEGORI[$kategori]['label'] }} @endif
        @isset($supplier) @if ($supplier) · Supplier: {{ $supplier->nama }} @endif @endisset
        @isset($barang) @if ($barang) · Barang: {{ $barang->nama }} @endif @endisset
    </div>

    @include($tabel)

    <div class="footer">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div>
</body>
</html>
