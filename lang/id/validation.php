<?php

/*
| Pesan validasi Bahasa Indonesia (yang umum dipakai aplikasi ini).
*/

return [
    'accepted' => ':attribute harus diterima.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'array' => ':attribute harus berupa daftar.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal sebelum atau sama dengan :date.',
    'between' => [
        'numeric' => ':attribute harus antara :min dan :max.',
        'string' => ':attribute harus antara :min dan :max karakter.',
        'array' => ':attribute harus memiliki :min sampai :max item.',
    ],
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Password saat ini salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_format' => ':attribute tidak sesuai format :format.',
    'decimal' => ':attribute harus memiliki :decimal angka desimal.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus :digits digit.',
    'digits_between' => ':attribute harus antara :min dan :max digit.',
    'distinct' => ':attribute memiliki nilai duplikat.',
    'email' => ':attribute harus alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'gt' => [
        'numeric' => ':attribute harus lebih besar dari :value.',
    ],
    'gte' => [
        'numeric' => ':attribute harus lebih besar atau sama dengan :value.',
    ],
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus bilangan bulat.',
    'lt' => [
        'numeric' => ':attribute harus kurang dari :value.',
    ],
    'lte' => [
        'numeric' => ':attribute harus kurang dari atau sama dengan :value.',
    ],
    'max' => [
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
        'array' => ':attribute tidak boleh lebih dari :max item.',
    ],
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
        'array' => ':attribute minimal :min item.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus mengandung minimal satu huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan kecil.',
        'numbers' => ':attribute harus mengandung minimal satu angka.',
        'symbols' => ':attribute harus mengandung minimal satu simbol.',
        'uncompromised' => ':attribute ini pernah bocor di internet. Gunakan :attribute lain.',
    ],
    'present' => ':attribute wajib ada.',
    'prohibited' => ':attribute tidak boleh diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi jika :other adalah :value.',
    'required_unless' => ':attribute wajib diisi kecuali :other adalah :values.',
    'required_with' => ':attribute wajib diisi jika :values ada.',
    'required_without' => ':attribute wajib diisi jika :values tidak ada.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'numeric' => ':attribute harus :size.',
        'string' => ':attribute harus :size karakter.',
    ],
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',

    'custom' => [],

    'attributes' => [
        'nama' => 'nama',
        'no_hp' => 'no. HP',
        'alamat' => 'alamat',
        'keterangan' => 'keterangan',
        'tanggal' => 'tanggal',
        'kode' => 'kode',
        'satuan' => 'satuan',
        'password' => 'password',
        'email' => 'email',
    ],
];
