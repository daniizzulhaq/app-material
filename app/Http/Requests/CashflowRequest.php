<?php

namespace App\Http\Requests;

use App\Models\Cashflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'tipe' => ['required', 'in:masuk,keluar'],
            // Input manual hanya untuk kategori non-otomatis
            'kategori' => ['required', Rule::in(Cashflow::KATEGORI_MANUAL)],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }
}
