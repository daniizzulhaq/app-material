{{-- Supplier = supplier barang --}}
@include('laporan.filter._supplier')
@include('laporan.filter._opsi', ['tanpaQty' => true, 'tanpaKlien' => true, 'modeHarga' => ['lengkap' => 'Dengan harga', 'tanpa' => 'Tanpa harga']])
