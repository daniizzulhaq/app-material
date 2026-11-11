{{--
    Laporan per klien. Tampilan awal: Klien · Kategori · Total Jual · Total Beli · Laba.
    Rincian barang muncul bila klien dipilih. $excel = true → angka mentah agar bisa dihitung di Excel.
--}}
@php
    $html = ! $excel && ! ($pdf ?? false);
    $uang = fn ($v) => $excel ? round((float) $v, 2) : rupiah($v);
    $labelKategori = fn ($k) => \App\Models\Klien::KATEGORI[$k]['label'] ?? '-';
@endphp

@if ($rincian->isNotEmpty())
    @include('laporan._rincian', [
        'judulRincian' => 'Rincian Barang',
        'kolomKlien' => false,
        'kolomSumber' => $opsi->beli(), // supplier hanya tampil bila harga beli ikut ditampilkan (laporan internal)
    ])
    @if ($excel)
        <table><tr><td><strong>Jumlah Keseluruhan</strong></td></tr></table>
    @else
        <div class="judul-rincian {{ $html ? 'px-3 pt-3 pb-2 fw-bold border-top' : '' }}">Jumlah Keseluruhan</div>
    @endif
@endif

<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Klien</th>
            <th>Kategori</th>
            @if ($opsi->jual()) <th class="text-end num">Total Jual</th> @endif
            @if ($opsi->beli()) <th class="text-end num">Total Beli</th> @endif
            @if ($opsi->laba()) <th class="text-end num">Laba</th> @endif
            @unless ($opsi->adaHarga()) <th class="text-end num">Jumlah Nota</th> @endunless
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>
                    @if ($html)
                        <a href="{{ request()->fullUrlWithQuery(['klien_id' => [$r->id], 'export' => null, 'unduh' => null]) }}" class="text-decoration-none" title="Lihat rincian barang">{{ $r->nama }}</a>
                    @else
                        {{ $r->nama }}
                    @endif
                </td>
                <td>{{ $labelKategori($r->kategori) }}</td>
                @if ($opsi->jual()) <td class="num">{{ $uang($r->total_jual) }}</td> @endif
                @if ($opsi->beli()) <td class="num">{{ $uang($r->total_beli) }}</td> @endif
                @if ($opsi->laba()) <td class="num {{ $html ? ($r->laba < 0 ? 'text-danger' : 'text-success') : '' }}">{{ $uang($r->laba) }}</td> @endif
                @unless ($opsi->adaHarga()) <td class="num">{{ $r->nota }}</td> @endunless
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        @foreach ($totalKategori ?? [] as $val => $t)
            <tr>
                <th colspan="2">Total Klien {{ $labelKategori($val) }}</th>
                @if ($opsi->jual()) <th class="num">{{ $uang($t['total_jual']) }}</th> @endif
                @if ($opsi->beli()) <th class="num">{{ $uang($t['total_beli']) }}</th> @endif
                @if ($opsi->laba()) <th class="num">{{ $uang($t['laba']) }}</th> @endif
                @unless ($opsi->adaHarga()) <th class="num">{{ $t['nota'] }}</th> @endunless
            </tr>
        @endforeach
        <tr>
            <th colspan="2">TOTAL{{ $kategori ? ' KLIEN ' . strtoupper($labelKategori($kategori)) : '' }}</th>
            @if ($opsi->jual()) <th class="num">{{ $uang($total['total_jual']) }}</th> @endif
            @if ($opsi->beli()) <th class="num">{{ $uang($total['total_beli']) }}</th> @endif
            @if ($opsi->laba()) <th class="num">{{ $uang($total['laba']) }}</th> @endif
            @unless ($opsi->adaHarga()) <th class="num">{{ $total['nota'] }}</th> @endunless
        </tr>
    </tfoot>
</table>
@if ($html)
    <p class="small text-muted px-3 py-2 mb-0"><i class="bi bi-info-circle me-1"></i>Klik nama klien (atau pilih klien di filter) untuk melihat rincian barangnya. Nama Klien "Sembunyikan" = rincian beberapa klien digabung jadi satu tabel.</p>
@endif
