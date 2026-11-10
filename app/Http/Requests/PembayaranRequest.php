<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisasiAngka;
use App\Models\PembelianStok;
use App\Models\Transaksi;
use App\Services\TagihanSupplierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Pembayaran klien: pelunasan nota.
 * Pembayaran supplier: pilih supplier, lalu tagihan yang dibayar:
 *  - "tanggal:2026-11-06" = belanja nota supplier tsb pada tanggal itu (semua klien digabung)
 *  - "faktur:12"          = faktur pembelian stok metode hutang
 */
class PembayaranRequest extends FormRequest
{
    use NormalisasiAngka;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = ['jumlah' => $this->uang($this->input('jumlah'))];

        if ($this->input('jenis') === 'supplier') {
            $tagihan = (string) $this->input('tagihan', '');
            if (str_starts_with($tagihan, 'tanggal:')) {
                $data['tanggal_nota'] = substr($tagihan, 8);
                $data['pembelian_stok_id'] = null;
            } elseif (str_starts_with($tagihan, 'faktur:')) {
                $data['pembelian_stok_id'] = substr($tagihan, 7);
                $data['tanggal_nota'] = null;
            }
            $data['transaksi_id'] = null;
        } else {
            $data += ['pembelian_stok_id' => null, 'tanggal_nota' => null, 'supplier_id' => null];
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'jenis' => ['required', 'in:klien,supplier'],
            'transaksi_id' => ['nullable', 'required_if:jenis,klien', 'exists:transaksis,id'],
            // supplier wajib untuk pembayaran belanja nota; untuk faktur diambil dari fakturnya
            'supplier_id' => ['nullable', 'required_with:tanggal_nota', 'exists:suppliers,id'],
            'pembelian_stok_id' => ['nullable', 'exists:pembelian_stoks,id'],
            'tanggal_nota' => ['nullable', 'date_format:Y-m-d', 'prohibits:pembelian_stok_id'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'metode' => ['required', 'in:tunai,transfer'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Tagihan wajib dipilih & jumlah bayar tidak boleh melebihi sisa tagihan */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $pembayaran = $this->route('pembayaran');
                $jumlahLama = $pembayaran ? (float) $pembayaran->jumlah : 0;

                if ($this->input('jenis') === 'klien') {
                    $tagihan = Transaksi::find($this->input('transaksi_id'));
                    $sisa = (float) $tagihan->total_jual - (float) $tagihan->pembayarans()->sum('jumlah');
                    if ($pembayaran && (int) $pembayaran->transaksi_id === $tagihan->id) {
                        $sisa += $jumlahLama;
                    }
                } elseif ($this->input('tanggal_nota')) {
                    // Belanja nota supplier per tanggal
                    $tagihan = app(TagihanSupplierService::class)
                        ->tanggal((int) $this->input('supplier_id'), $this->input('tanggal_nota'), $pembayaran?->id);
                    if (! $tagihan) {
                        $validator->errors()->add('tagihan', 'Tidak ada belanja nota untuk supplier ini pada tanggal tersebut.');

                        return;
                    }
                    $sisa = $tagihan->sisa;
                } elseif ($this->input('pembelian_stok_id')) {
                    $tagihan = PembelianStok::find($this->input('pembelian_stok_id'));
                    if ($tagihan->metode_bayar !== 'hutang') {
                        $validator->errors()->add('tagihan', 'Pembelian ini dibayar tunai, tidak perlu pembayaran hutang.');

                        return;
                    }
                    if ($this->filled('supplier_id') && (int) $this->input('supplier_id') !== (int) $tagihan->supplier_id) {
                        $validator->errors()->add('tagihan', 'Faktur ini bukan milik supplier yang dipilih.');

                        return;
                    }
                    $sisa = (float) $tagihan->total - (float) $tagihan->pembayarans()->sum('jumlah');
                    if ($pembayaran && (int) $pembayaran->pembelian_stok_id === $tagihan->id) {
                        $sisa += $jumlahLama;
                    }
                } else {
                    $validator->errors()->add($this->filled('supplier_id') ? 'tagihan' : 'supplier_id',
                        $this->filled('supplier_id') ? 'Pilih nota/faktur yang dibayar.' : 'Pilih supplier yang dibayar.');

                    return;
                }

                if ((float) $this->input('jumlah') > round($sisa, 2) + 0.001) {
                    $validator->errors()->add('jumlah', 'Jumlah melebihi sisa tagihan (' . rupiah($sisa) . ').');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'transaksi_id' => 'nota',
            'supplier_id' => 'supplier',
            'pembelian_stok_id' => 'faktur pembelian',
            'tanggal_nota' => 'tanggal nota',
        ];
    }

    public function messages(): array
    {
        return [
            'transaksi_id.required_if' => 'Pilih nota yang dibayar.',
            'supplier_id.required_with' => 'Pilih supplier yang dibayar.',
        ];
    }
}
