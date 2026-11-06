<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StokOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'barang_id' => ['required', 'exists:barangs,id'],
            'stok_fisik' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['barang_id' => 'barang', 'stok_fisik' => 'stok fisik'];
    }
}
