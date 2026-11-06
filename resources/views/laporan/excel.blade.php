{{-- Dibaca maatwebsite/excel (FromView): baris judul lalu tabel laporan --}}
<table>
    <tr><td><strong>{{ $judul }}</strong></td></tr>
    <tr><td>Periode {{ tanggal($dari) }} – {{ tanggal($sampai) }}@if (! empty($kategori)) · Kategori: Klien {{ \App\Models\Klien::KATEGORI[$kategori]['label'] }}@endif</td></tr>
    <tr><td></td></tr>
</table>
@include($tabel)
