{{-- Dibaca maatwebsite/excel (FromView): baris judul lalu tabel laporan --}}
@php
    $keterangan = 'Periode ' . tanggal($dari) . ' – ' . tanggal($sampai)
        . (! empty($kategori) ? ' · Kategori: Klien ' . \App\Models\Klien::KATEGORI[$kategori]['label'] : '')
        . (! empty($subjek) ? ' · ' . $subjek : '');
@endphp
<table>
    <tr><td><strong>{{ $judul }}</strong></td></tr>
    <tr><td>{{ $keterangan }}</td></tr>
    <tr><td></td></tr>
</table>
@include($tabel)
