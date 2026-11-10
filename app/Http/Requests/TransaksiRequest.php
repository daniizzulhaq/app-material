<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisasiAngka;
use Illuminate\Foundation\Http\FormRequest;

class TransaksiRequest extends FormRequest
{
    use NormalisasiAngka;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Harga diketik dengan titik ribuan ("64.000"), qty boleh koma ("2,5").
     * Baris yang benar-benar kosong (barang belum dipilih & tanpa angka) diabaikan.
     */
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('items'))) {
            return;
        }

        $items = collect($this->input('items'))
            ->filter(fn ($i) => is_array($i))
            ->reject(fn ($i) => blank($i['barang_id'] ?? null) && blank($i['qty'] ?? null)
                && blank($i['harga_jual'] ?? null) && blank($i['harga_beli'] ?? null))
            ->map(fn ($i) => array_merge($i, [
                'qty' => $this->desimal($i['qty'] ?? null),
                'harga_beli' => $this->uang($i['harga_beli'] ?? null),
                'harga_jual' => $this->uang($i['harga_jual'] ?? null),
            ]))
            ->all();

        $this->merge(['items' => $items]);
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
