<?php

namespace App\Http\Requests;

use App\Models\Barang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $barang = $this->route('barang');

        $rules = [
            'nama' => ['required', 'string', 'max:150'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'satuan' => ['required', 'string', 'max:20'],
            'harga_jual_default' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'stok_minimum' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];

        // Stok awal hanya saat barang baru dibuat; setelah itu stok berubah lewat transaksi/opname
        if (! $barang) {
            $rules['stok_awal'] = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
            $rules['hpp_awal'] = ['nullable', 'required_with:stok_awal', 'numeric', 'min:0', 'max:9999999999999'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'harga_jual_default' => 'harga jual default',
            'stok_minimum' => 'stok minimum',
            'stok_awal' => 'stok awal',
            'hpp_awal' => 'HPP awal',
        ];
    }
}
