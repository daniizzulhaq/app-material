@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<x-filter-periode :dari="$dari" :sampai="$sampai">
    <x-slot:aksi>
        <div class="btn-group">
            <a href="{{ route('dashboard', ['dari' => now()->toDateString(), 'sampai' => now()->toDateString()]) }}" class="btn btn-sm btn-light">Hari ini</a>
            <a href="{{ route('dashboard', ['dari' => now()->subDays(6)->toDateString(), 'sampai' => now()->toDateString()]) }}" class="btn btn-sm btn-light">7 hari</a>
            <a href="{{ route('dashboard', ['dari' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'sampai' => now()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}" class="btn btn-sm btn-light">Bulan lalu</a>
        </div>
    </x-slot:aksi>
</x-filter-periode>

{{-- Kartu statistik --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card label="Jumlah Nota" :value="number_format($nota->jumlah, 0, ',', '.')" icon="bi-receipt" color="blue" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Total Qty Terjual" :value="qty($nota->qty)" icon="bi-box-seam" color="purple" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Total Jual" :value="rupiah($nota->jual)" icon="bi-cash-coin" color="green" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Total Modal" :value="rupiah($nota->modal)" icon="bi-wallet2" color="yellow" /></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <x-stat-card label="Laba" :value="rupiah($nota->laba)" icon="bi-graph-up-arrow" :color="$nota->laba < 0 ? 'red' : 'green'"
                     :hint="$nota->jual > 0 ? 'Margin ' . number_format($nota->laba / $nota->jual * 100, 1, ',', '.') . '%' : null" />
    </div>
    <div class="col-12 col-md-4">
        <x-stat-card label="Belanja Supplier + Pembelian Stok" :value="rupiah($nota->belanja_supplier + $pembelianStok)" icon="bi-truck" color="red"
                     :hint="'Supplier ' . rupiah($nota->belanja_supplier) . ' · Stok ' . rupiah($pembelianStok)" />
    </div>
    <div class="col-12 col-md-4">
        <x-stat-card label="Nilai Stok Saat Ini" :value="rupiah($nilaiStok)" icon="bi-safe" color="purple" hint="stok × HPP seluruh barang" />
    </div>
</div>

{{-- Grafik --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title">Penjualan vs Laba per Hari</h6>
        <small class="text-muted">{{ tanggal($dari) }} – {{ tanggal($sampai) }}</small>
    </div>
    <div class="card-body">
        @if ($nota->jumlah > 0)
            <div style="height: 300px"><canvas id="grafik-penjualan" aria-label="Grafik penjualan dan laba per hari" role="img"></canvas></div>
        @else
            <x-empty-state icon="bi-graph-up" title="Belum ada penjualan" message="Grafik akan muncul setelah ada nota pada periode ini.">
                <a href="{{ route('transaksi.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Buat Nota</a>
            </x-empty-state>
        @endif
    </div>
</div>

{{-- Top 3 --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['judul' => 'Top 3 Supplier', 'icon' => 'bi-truck', 'rows' => $topSupplier, 'nilai' => 'total', 'sub' => fn ($r) => 'Nota ' . rupiah($r->total_beli) . ' · Stok ' . rupiah($r->pembelian_stok)],
        ['judul' => 'Top 3 Barang', 'icon' => 'bi-box-seam', 'rows' => $topBarang, 'nilai' => 'total_jual', 'sub' => fn ($r) => qty($r->qty) . ' ' . $r->satuan . ' · laba ' . rupiah($r->laba)],
        ['judul' => 'Top 3 Klien', 'icon' => 'bi-people', 'rows' => $topKlien, 'nilai' => 'total_jual', 'sub' => fn ($r) => $r->nota . ' nota · laba ' . rupiah($r->laba)],
    ] as $top)
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="card-title"><i class="bi {{ $top['icon'] }} me-2 text-primary"></i>{{ $top['judul'] }}</h6></div>
                <div class="card-body">
                    @forelse ($top['rows'] as $r)
                        <div class="d-flex align-items-center gap-3 {{ $loop->last ? '' : 'mb-3' }}">
                            <div class="stat-icon" style="width:36px;height:36px;font-size:.9rem">{{ $loop->iteration }}</div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $r->nama }}</div>
                                <div class="small text-muted text-truncate">{{ $top['sub']($r) }}</div>
                            </div>
                            <div class="fw-bold text-nowrap">{{ rupiah($r->{$top['nilai']}) }}</div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum ada data pada periode ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    {{-- 5 transaksi terakhir --}}
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">5 Transaksi Terakhir</h6>
                <a href="{{ route('transaksi.index') }}" class="small text-decoration-none">Lihat semua</a>
            </div>
            @if ($transaksiTerakhir->isEmpty())
                <x-empty-state icon="bi-receipt" title="Belum ada nota" class="py-4" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>Tanggal</th><th>Klien</th><th class="text-end">Total</th><th class="text-end">Laba</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($transaksiTerakhir as $t)
                                <tr>
                                    <td>
                                        <a href="{{ route('transaksi.show', $t) }}" class="fw-semibold text-decoration-none">{{ tanggal($t->tanggal) }}</a>
                                    </td>
                                    <td>{{ $t->klien->nama }}</td>
                                    <td class="num">{{ rupiah($t->total_jual) }}</td>
                                    <td class="num text-success">{{ rupiah($t->laba) }}</td>
                                    <td><x-status-badge :status="$t->status_bayar" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Stok menipis --}}
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Stok Menipis</h6>
                <a href="{{ route('stok.index', ['menipis' => 1]) }}" class="small text-decoration-none">Lihat semua</a>
            </div>
            @if ($stokMenipis->isEmpty())
                <x-empty-state icon="bi-check2-circle" title="Stok aman" message="Tidak ada barang di bawah stok minimum." class="py-4" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Barang</th><th class="text-end">Stok</th><th class="text-end">Min.</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($stokMenipis as $b)
                                <tr>
                                    <td class="fw-semibold">{{ $b->nama }}</td>
                                    <td class="num text-danger fw-semibold">{{ qty($b->stok) }} {{ $b->satuan }}</td>
                                    <td class="num text-muted">{{ qty($b->stok_minimum) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('pembelian-stok.create') }}" class="btn btn-sm btn-light" title="Beli stok"><i class="bi bi-cart-plus"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if ($nota->jumlah > 0)
<script type="module">
    const data = @json($grafik);
    new Chart(document.getElementById('grafik-penjualan'), {
        data: {
            labels: data.label,
            datasets: [
                { type: 'bar', label: 'Penjualan', data: data.jual, backgroundColor: 'rgba(79, 110, 247, .75)', borderRadius: 6, maxBarThickness: 32 },
                { type: 'line', label: 'Laba', data: data.laba, borderColor: '#22c55e', backgroundColor: '#22c55e', tension: .35, pointRadius: 3 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true } },
                tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatRupiah(ctx.parsed.y)}` } },
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: (v) => formatRupiah(v) }, grid: { color: '#eef0f4' } },
                x: { grid: { display: false } },
            },
        },
    });
</script>
@endif
@endpush
