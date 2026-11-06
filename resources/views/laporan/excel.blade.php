{{-- Dibaca maatwebsite/excel (FromView): baris judul lalu tabel laporan --}}
<table>
    <tr><td><strong>{{ $judul }}</strong></td></tr>
    <tr><td>Periode {{ tanggal($dari) }} – {{ tanggal($sampai) }}</td></tr>
    <tr><td></td></tr>
</table>
@include($tabel)
