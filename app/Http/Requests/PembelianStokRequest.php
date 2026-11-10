<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisasiAngka;
use Illuminate\Foundation\Http\FormRequest;

class PembelianStokRequest extends FormRequest
{
    use NormalisasiAngka;

    public function authorize(): bool
    {
        return true;
    }

    /** Harga beli diketik dengan titik ribuan ("64.000"), qty boleh koma ("2,5") */
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('items'))) {
            return;
        }

        $this->merge(['items' => collect($this->input('items'))
            ->map(fn ($i) => is_array($i) ? array_merge($i, [
                'qty' => $this->desimal($i['qty'] ?? null),
                'harga_beli' => $this->uang($i['harga_beli'] ?? null),
            ]) : $i)
            ->all()]);
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'metode_bayar' => ['required', 'in:tunai,hutang'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', 'exists:barangs,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'items.*.harga_beli' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'metode_bayar' => 'metode bayar',
            'items' => 'daftar barang',
            'items.*.barang_id' => 'barang',
            'items.*.qty' => 'qty',
            'items.*.harga_beli' => 'harga beli',
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'Tambahkan minimal 1 barang.'];
    }
}
