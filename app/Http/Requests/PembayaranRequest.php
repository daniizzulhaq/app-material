<?php

namespace App\Http\Requests;

use App\Models\PembelianStok;
use App\Models\Transaksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'jenis' => ['required', 'in:klien,supplier'],
            'transaksi_id' => ['nullable', 'required_if:jenis,klien', 'exists:transaksis,id'],
            'pembelian_stok_id' => ['nullable', 'required_if:jenis,supplier', 'exists:pembelian_stoks,id'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'metode' => ['required', 'in:tunai,transfer'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Jumlah bayar tidak boleh melebihi sisa tagihan */
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
                } else {
                    $tagihan = PembelianStok::find($this->input('pembelian_stok_id'));
                    if ($tagihan->metode_bayar !== 'hutang') {
                        $validator->errors()->add('pembelian_stok_id', 'Pembelian ini dibayar tunai, tidak perlu pembayaran hutang.');

                        return;
                    }
                    $sisa = (float) $tagihan->total - (float) $tagihan->pembayarans()->sum('jumlah');
                    if ($pembayaran && (int) $pembayaran->pembelian_stok_id === $tagihan->id) {
                        $sisa += $jumlahLama;
                    }
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
            'pembelian_stok_id' => 'faktur pembelian',
        ];
    }

    public function messages(): array
    {
        return [
            'transaksi_id.required_if' => 'Pilih nota yang dibayar.',
            'pembelian_stok_id.required_if' => 'Pilih faktur pembelian yang dibayar.',
        ];
    }
}
