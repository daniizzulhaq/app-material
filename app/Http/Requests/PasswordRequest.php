<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordRequest extends FormRequest
{
    /** Error ditampilkan di form password (bukan form profil) */
    protected $errorBag = 'password';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:password_lama'],
        ];
    }

    public function attributes(): array
    {
        return [
            'password_lama' => 'password lama',
            'password' => 'password baru',
        ];
    }

    public function messages(): array
    {
        return ['password_lama.current_password' => 'Password lama tidak sesuai.'];
    }
}
