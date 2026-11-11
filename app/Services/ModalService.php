<?php

namespace App\Services;

use App\Models\Cashflow;
use App\Models\Pembayaran;
use App\Models\Transaksi;

/**
 * Perputaran modal (terpisah dari saldo kas):
 *   modal tersedia = setoran modal (cashflow manual kategori Modal: masuk +, keluar −)
 *                  − total beli nota per hari (total modal nota: belanja supplier + HPP barang stok)
 *                  + bagian harga beli yang sudah dibayar klien (proporsional, per pembayaran yang dikaitkan ke nota).
 * Contoh: nota beli 400.000 / jual 500.000 dibayar 250.000 → modal kembali 200.000. Laba tidak dihitung sebagai modal kembali.
 * Dihitung dari data aktif, jadi edit/hapus nota & pembayaran langsung tercermin.
 */
class ModalService
{
    public function ringkasan(string $dari, string $sampai): array
    {
        $harian = [];
        $tambah = function (string $tanggal, string $kolom, float $nilai) use (&$harian) {
            $harian[$tanggal] ??= ['tanggal' => $tanggal, 'tambahan' => 0.0, 'belanja' => 0.0, 'kembali' => 0.0];
            $harian[$tanggal][$kolom] += $nilai;
        };

        $setoran = Cashflow::query()
            ->whereNull('referensi_tipe')
            ->where('kategori', 'modal')
            ->whereDate('tanggal', '<=', $sampai)
            ->groupBy('tanggal')
            ->selectRaw("tanggal, SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE -nominal END) AS total")
            ->get();
        foreach ($setoran as $row) {
            $tambah($row->tanggal->toDateString(), 'tambahan', (float) $row->total);
        }

        $notas = Transaksi::whereDate('tanggal', '<=', $sampai)->get(['id', 'tanggal', 'total_modal', 'total_jual'])->keyBy('id');
        foreach ($notas as $nota) {
            $tambah($nota->tanggal->toDateString(), 'belanja', (float) $nota->total_modal);
        }

        // Modal kembali dari proporsi kumulatif, supaya cicilan kecil tidak meninggalkan selisih pembulatan
        $dibayar = [];
        $pembayarans = Pembayaran::query()
            ->where('jenis', 'klien')
            ->whereIn('transaksi_id', $notas->keys())
            ->whereDate('tanggal', '<=', $sampai)
            ->orderBy('tanggal')->orderBy('id')
            ->get(['transaksi_id', 'tanggal', 'jumlah']);
        foreach ($pembayarans as $p) {
            $nota = $notas[$p->transaksi_id];
            if ((float) $nota->total_jual <= 0) {
                continue;
            }
            $bagian = fn (float $bayar) => round((float) $nota->total_modal * min(1, $bayar / (float) $nota->total_jual), 2);
            $sebelum = $dibayar[$nota->id] ?? 0.0;
            $sesudah = $sebelum + (float) $p->jumlah;
            $tambah($p->tanggal->toDateString(), 'kembali', $bagian($sesudah) - $bagian($sebelum));
            $dibayar[$nota->id] = $sesudah;
        }

        ksort($harian);
        $saldo = 0.0;
        $awal = 0.0;
        $rows = [];
        foreach ($harian as $tanggal => $row) {
            $saldo += $row['tambahan'] - $row['belanja'] + $row['kembali'];
            if ($tanggal < $dari) {
                $awal = $saldo;
            } else {
                $rows[] = (object) ($row + ['saldo' => $saldo]);
            }
        }
        $rows = collect($rows);

        return [
            'saldo_awal' => $awal,
            'tambahan' => (float) $rows->sum('tambahan'),
            'belanja' => (float) $rows->sum('belanja'),
            'kembali' => (float) $rows->sum('kembali'),
            'saldo_akhir' => $saldo,
            'harian' => $rows,
        ];
    }
}
