<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Klien;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Supplier;
use App\Models\TransaksiDetail;
use App\Support\OpsiLaporan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Query agregat untuk semua laporan (dipakai halaman, PDF, dan Excel) */
class LaporanService
{
    /**
     * Query item nota (detail + header) dalam periode, mengikuti filter klien / kategori / supplier / barang.
     * Filter supplier = item yang dibeli dari supplier tsb (sumber supplier).
     */
    private function detailQuery(string $dari, string $sampai, array $f = [])
    {
        return DB::table('transaksi_details as d')
            ->join('transaksis as t', 't.id', '=', 'd.transaksi_id')
            ->whereBetween('t.tanggal', [$dari, $sampai])
            ->when($f['klien'] ?? [], fn ($q, $ids) => $q->whereIn('t.klien_id', $ids))
            ->when($f['kategori'] ?? null, fn ($q, $k) => $q->whereIn('t.klien_id', Klien::kategori($k)->select('id')))
            ->when($f['supplier'] ?? [], fn ($q, $ids) => $q->whereIn('d.supplier_id', $ids))
            ->when($f['barang'] ?? [], fn ($q, $ids) => $q->whereIn('d.barang_id', $ids))
            ->when($f['sumber'] ?? null, fn ($q, $v) => $q->where('d.sumber', $v));
    }

    /** Agregat nota, qty, beli, jual per kolom kelompok */
    private function agregat($query, array $kolom): Collection
    {
        return $query->groupBy($kolom)
            ->get(array_merge($kolom, [
                DB::raw('COUNT(DISTINCT d.transaksi_id) AS nota'),
                DB::raw('SUM(d.qty) AS qty'),
                DB::raw("SUM(CASE WHEN d.sumber = 'supplier' THEN d.qty ELSE 0 END) AS qty_supplier"),
                DB::raw("SUM(CASE WHEN d.sumber = 'stok' THEN d.qty ELSE 0 END) AS qty_stok"),
                DB::raw('SUM(d.subtotal_beli) AS beli'),
                DB::raw('SUM(d.subtotal_jual) AS jual'),
            ]));
    }

    private function angka(?object $r): array
    {
        $beli = (float) ($r->beli ?? 0);
        $jual = (float) ($r->jual ?? 0);

        return [
            'nota' => (int) ($r->nota ?? 0),
            'qty' => (float) ($r->qty ?? 0),
            'qty_supplier' => (float) ($r->qty_supplier ?? 0),
            'qty_stok' => (float) ($r->qty_stok ?? 0),
            'total_beli' => $beli,
            'total_jual' => $jual,
            'laba' => $jual - $beli,
        ];
    }

    /**
     * Rincian barang (item nota) dalam periode, mengikuti filter.
     * Barang yang sama (sumber & supplier sama; klien sama bila $pisahKlien) di tanggal yang sama digabung jadi satu baris
     * (mis. pesanan awal + tambahan). Qty tiap pesanan disimpan di "pecahan" (format qty 1 + 1).
     */
    private function rincianItems(string $dari, string $sampai, array $f, bool $pisahKlien): Collection
    {
        $items = TransaksiDetail::query()
            ->select('transaksi_details.*', 'transaksis.tanggal', 'transaksis.klien_id')
            ->join('transaksis', 'transaksis.id', '=', 'transaksi_details.transaksi_id')
            ->whereBetween('transaksis.tanggal', [$dari, $sampai])
            ->when($f['klien'] ?? [], fn ($q, $ids) => $q->whereIn('transaksis.klien_id', $ids))
            ->when($f['kategori'] ?? null, fn ($q, $k) => $q->whereIn('transaksis.klien_id', Klien::kategori($k)->select('id')))
            ->when($f['supplier'] ?? [], fn ($q, $ids) => $q->whereIn('transaksi_details.supplier_id', $ids))
            ->when($f['barang'] ?? [], fn ($q, $ids) => $q->whereIn('transaksi_details.barang_id', $ids))
            ->when($f['sumber'] ?? null, fn ($q, $v) => $q->where('transaksi_details.sumber', $v))
            ->with(['barang:id,nama,satuan', 'supplier:id,nama', 'transaksi:id,klien_id', 'transaksi.klien:id,nama'])
            ->orderBy('transaksis.tanggal')->orderBy('transaksi_details.transaksi_id')->orderBy('transaksi_details.id')
            ->get();

        return $items
            ->groupBy(fn ($d) => implode('|', [
                substr((string) $d->tanggal, 0, 10), $pisahKlien ? $d->klien_id : '*', $d->barang_id, $d->sumber, $d->supplier_id,
            ]))
            ->map(function (Collection $grup) {
                $baris = clone $grup->first();
                $baris->qty = $grup->sum(fn ($d) => (float) $d->qty);
                $baris->subtotal_beli = $grup->sum(fn ($d) => (float) $d->subtotal_beli);
                $baris->subtotal_jual = $grup->sum(fn ($d) => (float) $d->subtotal_jual);
                $baris->pecahan = $grup->map(fn ($d) => (float) $d->qty)->all();
                $baris->harga_beli_list = $grup->map(fn ($d) => (float) $d->harga_beli)->unique()->values()->all();
                $baris->harga_jual_list = $grup->map(fn ($d) => (float) $d->harga_jual)->unique()->values()->all();

                return $baris;
            })
            ->values();
    }

    /**
     * Per klien. Tampilan awal: Klien · Kategori · Total Jual · Total Beli · Laba (tanpa dibayar / sisa).
     * Rincian barang ditampilkan bila klien dipilih: per klien (klien ditampilkan) atau digabung (klien disembunyikan).
     *
     * @param  int[]  $klienIds
     * @param  int[]  $supplierIds
     */
    public function klien(string $dari, string $sampai, array $klienIds, ?string $kategori, array $supplierIds, OpsiLaporan $o): array
    {
        $kategori = array_key_exists((string) $kategori, Klien::KATEGORI) ? $kategori : null;
        $f = ['klien' => $klienIds, 'kategori' => $kategori, 'supplier' => $supplierIds];

        $agg = $this->agregat($this->detailQuery($dari, $sampai, $f), ['t.klien_id'])->keyBy('klien_id');
        // Klien yang dipilih selalu tampil, walaupun tidak ada nota di periode ini
        $ids = $agg->keys()->merge($klienIds)->unique();
        $kliens = Klien::whereIn('id', $ids)->get(['id', 'nama', 'kategori'])->keyBy('id');

        $rows = $ids->map(fn ($id) => (object) ([
            'id' => (int) $id,
            'nama' => $kliens[$id]->nama ?? '-',
            'kategori' => $kliens[$id]->kategori ?? null,
        ] + $this->angka($agg[$id] ?? null)))->sortBy('nama')->values();

        $kolom = ['nota', 'total_jual', 'total_beli', 'laba'];

        // Rincian barang hanya saat klien dipilih (tampilan awal cukup ringkasan per klien)
        $rincian = collect();
        if ($klienIds) {
            $items = $this->rincianItems($dari, $sampai, $f, $o->tampilKlien);
            $rincian = $o->tampilKlien
                ? $rows->map(fn ($r) => (object) ['nama' => $r->nama, 'items' => $items->where('klien_id', $r->id)->values()])
                : collect([(object) ['nama' => $rows->pluck('nama')->implode(', '), 'items' => $items]]);
        }

        return [
            'rows' => $rows,
            'rincian' => $rincian,
            'kategori' => $kategori,
            'total' => $this->jumlahkan($rows, $kolom),
            // Subtotal per kategori (hanya saat rekap semua klien tanpa filter kategori)
            'totalKategori' => ($klienIds || $kategori) ? [] : collect(Klien::KATEGORI)
                ->map(fn ($k, $val) => $this->jumlahkan($rows->where('kategori', $val), $kolom))->all(),
        ];
    }

    /**
     * Per supplier: daftar barang yang dibeli dari tiap supplier lewat nota (rincian, seperti web sayur)
     * + ringkasan per supplier. Belanja nota lunas otomatis per hari, jadi tidak ada sisa hutang nota.
     * Pembelian stok (tidak terkait klien) ikut di ringkasan bila filter klien tidak aktif.
     *
     * @param  int[]  $supplierIds
     * @param  int[]  $klienIds
     */
    public function supplier(string $dari, string $sampai, array $supplierIds, array $klienIds, OpsiLaporan $o): array
    {
        $f = ['supplier' => $supplierIds, 'klien' => $klienIds, 'sumber' => 'supplier'];

        $agg = $this->agregat($this->detailQuery($dari, $sampai, $f), ['d.supplier_id'])->keyBy('supplier_id');

        $bayarHutang = Pembayaran::query()
            ->whereNotNull('pembelian_stok_id')
            ->selectRaw('pembelian_stok_id, SUM(jumlah) AS dibayar')
            ->groupBy('pembelian_stok_id');

        $pembelian = PembelianStok::query()
            ->leftJoinSub($bayarHutang, 'bayar', 'bayar.pembelian_stok_id', '=', 'pembelian_stoks.id')
            ->whereBetween('pembelian_stoks.tanggal', [$dari, $sampai])
            ->when($supplierIds, fn ($q) => $q->whereIn('pembelian_stoks.supplier_id', $supplierIds))
            ->when($klienIds, fn ($q) => $q->whereRaw('1 = 0'))
            ->selectRaw("
                pembelian_stoks.supplier_id,
                SUM(pembelian_stoks.total) AS total,
                SUM(CASE WHEN pembelian_stoks.metode_bayar = 'tunai' THEN pembelian_stoks.total ELSE COALESCE(bayar.dibayar, 0) END) AS dibayar
            ")
            ->groupBy('pembelian_stoks.supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $ids = $agg->keys()->merge($pembelian->keys())->merge($supplierIds)->unique();
        $suppliers = Supplier::whereIn('id', $ids)->pluck('nama', 'id');

        $rows = $ids->map(function ($id) use ($agg, $pembelian, $suppliers) {
            $stok = (float) ($pembelian[$id]->total ?? 0);

            return (object) ([
                'id' => (int) $id,
                'nama' => $suppliers[$id] ?? '-',
            ] + $this->angka($agg[$id] ?? null) + [
                'pembelian_stok' => $stok,
                'sisa_hutang' => max(0, $stok - (float) ($pembelian[$id]->dibayar ?? 0)),
            ]);
        })->sortBy('nama')->values();

        // Daftar barang per supplier (supplier tanpa barang di nota dilewati, kecuali dipilih di filter)
        $items = $this->rincianItems($dari, $sampai, $f, $o->tampilKlien);
        $rincian = $rows
            ->filter(fn ($r) => $r->nota > 0 || in_array($r->id, $supplierIds, true))
            ->map(fn ($r) => (object) ['nama' => $r->nama, 'items' => $items->where('supplier_id', $r->id)->values()])
            ->values();

        return [
            'rows' => $rows,
            'rincian' => $rincian,
            'supplier' => count($supplierIds) === 1 ? Supplier::find($supplierIds[0]) : null,
            'total' => $this->jumlahkan($rows, ['nota', 'qty', 'total_beli', 'total_jual', 'laba', 'pembelian_stok', 'sisa_hutang']),
            'filterKlien' => (bool) $klienIds,
        ];
    }

    /**
     * Per barang: qty terjual (dari supplier vs dari stok), beli, jual, laba.
     * Klien ditampilkan → satu baris per barang per klien.
     */
    public function barang(string $dari, string $sampai, ?int $barangId, array $klienIds, array $supplierIds, OpsiLaporan $o): array
    {
        $f = ['barang' => $barangId ? [$barangId] : [], 'klien' => $klienIds, 'supplier' => $supplierIds];
        $kolom = $o->tampilKlien ? ['d.barang_id', 't.klien_id'] : ['d.barang_id'];

        $agregat = $this->agregat($this->detailQuery($dari, $sampai, $f), $kolom);

        $barangs = Barang::whereIn('id', $agregat->pluck('barang_id'))->get(['id', 'nama', 'satuan'])->keyBy('id');
        $kliens = $o->tampilKlien ? Klien::whereIn('id', $agregat->pluck('klien_id'))->pluck('nama', 'id') : collect();

        $rows = $agregat->map(fn ($r) => (object) ([
            'nama' => $barangs[$r->barang_id]->nama ?? '-',
            'satuan' => $barangs[$r->barang_id]->satuan ?? '',
            'klien' => $o->tampilKlien ? ($kliens[$r->klien_id] ?? '-') : null,
        ] + $this->angka($r)))
            ->sortBy(fn ($r) => $r->nama . '|' . $r->klien)
            ->values();

        return [
            'rows' => $rows,
            'barang' => $barangId ? Barang::find($barangId) : null,
            'total' => $this->jumlahkan($rows, ['total_beli', 'total_jual', 'laba']),
        ];
    }

    /** Laporan stok: stok awal, masuk, keluar, penyesuaian, stok akhir, nilai stok akhir */
    public function stok(string $dari, string $sampai, array $supplierIds = []): array
    {
        $mutasi = DB::table('stok_mutasis')
            ->selectRaw("
                barang_id,
                SUM(CASE WHEN tanggal < ? THEN (CASE WHEN tipe = 'keluar' THEN -qty ELSE qty END) ELSE 0 END) AS stok_awal,
                SUM(CASE WHEN tanggal BETWEEN ? AND ? AND tipe = 'masuk' THEN qty ELSE 0 END) AS masuk,
                SUM(CASE WHEN tanggal BETWEEN ? AND ? AND tipe = 'keluar' THEN qty ELSE 0 END) AS keluar,
                SUM(CASE WHEN tanggal BETWEEN ? AND ? AND tipe = 'penyesuaian' THEN qty ELSE 0 END) AS penyesuaian
            ", [$dari, $dari, $sampai, $dari, $sampai, $dari, $sampai])
            ->where('tanggal', '<=', $sampai)
            ->groupBy('barang_id')
            ->get()
            ->keyBy('barang_id');

        $nilaiOpname = DB::table('stok_opnames')
            ->whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('barang_id, SUM(nilai_selisih) AS nilai')
            ->groupBy('barang_id')
            ->pluck('nilai', 'barang_id');

        // Filter supplier = supplier barang (bisa lebih dari satu)
        $rows = Barang::diStok()->when($supplierIds, fn ($q) => $q->whereIn('supplier_id', $supplierIds))->orderBy('nama')->get()->map(function ($b) use ($mutasi, $nilaiOpname) {
            $m = $mutasi[$b->id] ?? null;
            $awal = (float) ($m->stok_awal ?? 0);
            $masuk = (float) ($m->masuk ?? 0);
            $keluar = (float) ($m->keluar ?? 0);
            $penyesuaian = (float) ($m->penyesuaian ?? 0);
            $akhir = $awal + $masuk - $keluar + $penyesuaian;

            return (object) [
                'nama' => $b->nama,
                'satuan' => $b->satuan,
                'stok_awal' => $awal,
                'masuk' => $masuk,
                'keluar' => $keluar,
                'penyesuaian' => $penyesuaian,
                'stok_akhir' => $akhir,
                'hpp' => (float) $b->hpp,
                // Nilai stok akhir memakai HPP terkini
                'nilai_akhir' => round($akhir * (float) $b->hpp, 2),
                'nilai_penyesuaian' => (float) ($nilaiOpname[$b->id] ?? 0),
            ];
        });

        return [
            'rows' => $rows,
            'total' => $this->jumlahkan($rows, ['nilai_akhir', 'nilai_penyesuaian']),
        ];
    }

    private function jumlahkan(Collection $rows, array $kolom): array
    {
        return collect($kolom)->mapWithKeys(fn ($k) => [$k => $rows->sum($k)])->all();
    }
}
