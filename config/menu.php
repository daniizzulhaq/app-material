<?php

/*
|--------------------------------------------------------------------------
| Menu Sidebar
|--------------------------------------------------------------------------
| 'route'  : nama route tujuan (link otomatis nonaktif jika route belum ada)
| 'active' : pola nama route untuk menandai menu aktif
*/

return [
    [
        'group' => null,
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'route' => 'dashboard', 'active' => 'dashboard'],
        ],
    ],
    [
        'group' => 'Master',
        'items' => [
            ['label' => 'Supplier', 'icon' => 'bi-truck', 'route' => 'supplier.index', 'active' => 'supplier.*'],
            ['label' => 'Barang', 'icon' => 'bi-box-seam', 'route' => 'barang.index', 'active' => 'barang.*'],
            ['label' => 'Klien', 'icon' => 'bi-people', 'route' => 'klien.index', 'active' => 'klien.*'],
        ],
    ],
    [
        'group' => 'Stok',
        'items' => [
            ['label' => 'Pembelian Stok', 'icon' => 'bi-cart-plus', 'route' => 'pembelian-stok.index', 'active' => 'pembelian-stok.*'],
            ['label' => 'Stok Barang', 'icon' => 'bi-boxes', 'route' => 'stok.index', 'active' => 'stok.*'],
            ['label' => 'Stok Opname', 'icon' => 'bi-clipboard-check', 'route' => 'stok-opname.index', 'active' => 'stok-opname.*'],
        ],
    ],
    [
        'group' => 'Transaksi',
        'items' => [
            ['label' => 'Rekapan (Nota)', 'icon' => 'bi-receipt', 'route' => 'transaksi.index', 'active' => 'transaksi.*'],
        ],
    ],
    [
        'group' => 'Laporan',
        'items' => [
            ['label' => 'Laporan Per Klien', 'icon' => 'bi-person-lines-fill', 'route' => 'laporan.klien', 'active' => 'laporan.klien*'],
            ['label' => 'Laporan Per Supplier', 'icon' => 'bi-building', 'route' => 'laporan.supplier', 'active' => 'laporan.supplier*'],
            ['label' => 'Laporan Per Barang', 'icon' => 'bi-bar-chart', 'route' => 'laporan.barang', 'active' => 'laporan.barang*'],
            ['label' => 'Laporan Stok', 'icon' => 'bi-clipboard-data', 'route' => 'laporan.stok', 'active' => 'laporan.stok*'],
        ],
    ],
    [
        'group' => 'Keuangan',
        'items' => [
            ['label' => 'Pembayaran', 'icon' => 'bi-credit-card', 'route' => 'pembayaran.index', 'active' => 'pembayaran.*'],
            ['label' => 'Cashflow', 'icon' => 'bi-cash-stack', 'route' => 'cashflow.index', 'active' => 'cashflow.*'],
        ],
    ],
    [
        'group' => 'Pengaturan',
        'items' => [
            ['label' => 'Riwayat Hapus', 'icon' => 'bi-clock-history', 'route' => 'riwayat.index', 'active' => 'riwayat.*'],
            ['label' => 'User', 'icon' => 'bi-person-gear', 'route' => 'user.edit', 'active' => 'user.*'],
        ],
    ],
];
