<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\Pembayaran;
use App\Models\PembelianStok;
use App\Models\Supplier;
use App\Models\TransaksiDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tagihan ke supplier:
 *  - belanja nota (item sumber supplier) dari semua klien, digabung per tanggal nota
 *  - pembelian stok dengan metode hutang
 */
class TagihanSupplierService
{
    /**
     * Total belanja nota supplier per tanggal + yang sudah dibayar + sisa.
     * $abaikanPembayaran = id pembayaran yang sedang diedit (tidak dihitung sebagai "sudah dibayar").
     */
    public function perTanggal(int $supplierId, ?int $abaikanPembayaran = null): Collection
    {
        $dibayar = Pembayaran::query()
            ->where('jenis', 'supplier')
            ->where('supplier_id', $supplierId)
            ->whereNotNull('tanggal_nota')
            ->when($abaikanPembayaran, fn ($q) => $q->where('id', '!=', $abaikanPembayaran))
            ->groupBy('tanggal_nota')
            ->selectRaw('tanggal_nota, SUM(jumlah) AS total')
            ->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->tanggal_nota, 0, 10) => (float) $r->total]);

        return TransaksiDetail::query()
            ->join('transaksis as t', 't.id', '=', 'transaksi_details.transaksi_id')
            ->where('transaksi_details.sumber', 'supplier')
            ->where('transaksi_details.supplier_id', $supplierId)
            ->groupBy('t.tanggal')
            ->orderByDesc('t.tanggal')
            ->get(['t.tanggal', DB::raw('SUM(transaksi_details.subtotal_beli) AS total'), DB::raw('COUNT(DISTINCT t.id) AS nota')])
            ->map(function ($row) use ($dibayar) {
                $tanggal = substr((string) $row->tanggal, 0, 10);

                return (object) [
                    'tanggal' => $tanggal,
                    'nota' => (int) $row->nota,
                    'total' => round((float) $row->total, 2),
                    'dibayar' => round($dibayar[$tanggal] ?? 0, 2),
                    'sisa' => round(max(0, (float) $row->total - ($dibayar[$tanggal] ?? 0)), 2),
                ];
            });
    }

    /** Tagihan satu tanggal (null bila supplier tidak punya belanja nota di tanggal tsb). */
    public function tanggal(int $supplierId, string $tanggal, ?int $abaikanPembayaran = null): ?object
    {
        return $this->perTanggal($supplierId, $abaikanPembayaran)->firstWhere('tanggal', $tanggal);
    }

    /** Faktur pembelian stok hutang milik supplier yang masih punya sisa (+ faktur yang sedang dipilih). */
    public function faktur(int $supplierId, ?int $pilih = null, ?Pembayaran $pembayaran = null): Collection
    {
        return PembelianStok::query()
            ->withSum('pembayarans', 'jumlah')
            ->where('supplier_id', $supplierId)
            ->where('metode_bayar', 'hutang')
            ->where(fn ($q) => $q->where('status_bayar', '!=', 'lunas')->when($pilih, fn ($x) => $x->orWhere('id', $pilih)))
            ->latest('tanggal')
            ->get()
            ->map(fn ($p) => (object) [
                'id' => $p->id,
                'no_faktur' => $p->no_faktur,
                'tanggal' => $p->tanggal->toDateString(),
                'total' => (float) $p->total,
                'sisa' => $p->sisaHutang() + ($pembayaran && $pembayaran->pembelian_stok_id == $p->id ? (float) $pembayaran->jumlah : 0),
            ]);
    }

    /**
     * Pastikan pembayaran belanja nota per tanggal tidak melebihi total belanja
     * (dipanggil setelah nota diubah/dihapus, di dalam transaksi DB).
     *
     * @param  iterable<array{0:int,1:string}>  $pasangan  [supplier_id, tanggal]
     */
    public function pastikanTidakLebih(iterable $pasangan): void
    {
        foreach (collect($pasangan)->unique(fn ($p) => $p[0] . '|' . $p[1]) as [$supplierId, $tanggal]) {
            $dibayar = (float) Pembayaran::where('jenis', 'supplier')->where('supplier_id', $supplierId)
                ->whereDate('tanggal_nota', $tanggal)->sum('jumlah');
            if ($dibayar <= 0) {
                continue;
            }

            $belanja = (float) TransaksiDetail::query()
                ->join('transaksis as t', 't.id', '=', 'transaksi_details.transaksi_id')
                ->where('transaksi_details.sumber', 'supplier')
                ->where('transaksi_details.supplier_id', $supplierId)
                ->whereDate('t.tanggal', $tanggal)
                ->sum('transaksi_details.subtotal_beli');

            if ($dibayar > round($belanja, 2) + 0.001) {
                $nama = Supplier::whereKey($supplierId)->value('nama') ?? 'supplier';
                throw new BisnisException(sprintf(
                    'Belanja ke %s tanggal %s menjadi %s, lebih kecil dari pembayaran yang sudah dicatat (%s). Sesuaikan/hapus pembayarannya dulu.',
                    $nama, tanggal($tanggal), rupiah($belanja), rupiah($dibayar)
                ));
            }
        }
    }
}
