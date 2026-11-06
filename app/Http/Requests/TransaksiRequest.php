<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'klien_id' => ['required', 'exists:kliens,id'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', 'exists:barangs,id'],
            'items.*.sumber' => ['required', 'in:supplier,stok'],
            // supplier wajib jika sumber = supplier
            'items.*.supplier_id' => ['nullable', 'required_if:items.*.sumber,supplier', 'exists:suppliers,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            // harga beli item stok diabaikan: server memakai HPP
            'items.*.harga_beli' => ['nullable', 'required_if:items.*.sumber,supplier', 'numeric', 'min:0', 'max:9999999999999'],
            'items.*.harga_jual' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'klien_id' => 'klien',
            'items' => 'item nota',
            'items.*.barang_id' => 'barang',
            'items.*.sumber' => 'sumber',
            'items.*.supplier_id' => 'supplier',
            'items.*.qty' => 'qty',
            'items.*.harga_beli' => 'harga beli',
            'items.*.harga_jual' => 'harga jual',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Tambahkan minimal 1 item.',
            'items.*.supplier_id.required_if' => 'Supplier wajib dipilih untuk item dari supplier.',
            'items.*.harga_beli.required_if' => 'Harga beli wajib diisi untuk item dari supplier.',
        ];
    }
}
