<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisasiAngka;
use App\Models\PembelianStok;
use App\Models\Transaksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Pembayaran klien (uang masuk): pilih klien, lalu (opsional) kaitkan ke nota klien tsb.
 * Pembayaran supplier hanya untuk pelunasan hutang pembelian stok (pembelian_stok_id wajib).
 * Belanja supplier dari nota tidak dibayar di sini: sudah lunas otomatis per hari.
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
        $jenis = $this->input('jenis') === 'supplier' ? 'supplier' : 'klien';

        $this->merge([
            'jenis' => $jenis,
            'jumlah' => $this->uang($this->input('jumlah')),
            'klien_id' => $jenis === 'klien' ? $this->input('klien_id') : null,
            'transaksi_id' => $jenis === 'klien' ? ($this->input('transaksi_id') ?: null) : null,
            'pembelian_stok_id' => $jenis === 'supplier' ? $this->input('pembelian_stok_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'jenis' => ['required', 'in:klien,supplier'],
            'klien_id' => ['nullable', 'required_if:jenis,klien', 'exists:kliens,id'],
            'transaksi_id' => ['nullable', 'exists:transaksis,id'],
            'pembelian_stok_id' => ['nullable', 'required_if:jenis,supplier', 'exists:pembelian_stoks,id'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'metode' => ['required', 'in:tunai,transfer'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Nota harus milik klien terpilih; jumlah tidak boleh melebihi sisa nota / sisa hutang. */
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
                    if (! $this->input('transaksi_id')) {
                        return; // pembayaran klien tanpa nota
                    }
                    $tagihan = Transaksi::find($this->input('transaksi_id'));
                    if ((int) $tagihan->klien_id !== (int) $this->input('klien_id')) {
                        $validator->errors()->add('transaksi_id', 'Nota ini bukan milik klien yang dipilih.');

                        return;
                    }
                    $sisa = (float) $tagihan->total_jual - (float) $tagihan->pembayarans()->sum('jumlah');
                    if ($pembayaran && (int) $pembayaran->transaksi_id === $tagihan->id) {
                        $sisa += $jumlahLama;
                    }
                    $pesan = 'Jumlah melebihi sisa tagihan nota';
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
                    $pesan = 'Jumlah melebihi sisa hutang';
                }

                if ((float) $this->input('jumlah') > round($sisa, 2) + 0.001) {
                    $validator->errors()->add('jumlah', $pesan . ' (' . rupiah(max(0, $sisa)) . ').');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'klien_id' => 'klien',
            'transaksi_id' => 'nota',
            'pembelian_stok_id' => 'faktur pembelian',
        ];
    }

    public function messages(): array
    {
        return [
            'klien_id.required_if' => 'Pilih klien yang membayar.',
            'pembelian_stok_id.required_if' => 'Pilih faktur pembelian yang dibayar.',
        ];
    }
}
