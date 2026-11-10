<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisasiAngka;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input barang hanya data master: nama, supplier, satuan, harga jual & harga beli.
 * Barang TIDAK otomatis masuk data stok; stok hanya bertambah lewat Pembelian Stok.
 */
class BarangRequest extends FormRequest
{
    use NormalisasiAngka;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalisasiUang(['harga_jual_default', 'harga_beli_default']);
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'satuan' => ['required', 'string', 'max:20'],
            'harga_jual_default' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'harga_beli_default' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key === null) {
            $data['harga_beli_default'] = $data['harga_beli_default'] ?? 0;
        }

        return $data;
    }

    public function attributes(): array
    {
        return [
            'harga_jual_default' => 'harga jual',
            'harga_beli_default' => 'harga beli',
        ];
    }
}
