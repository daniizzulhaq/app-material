<?php

namespace App\Http\Requests\Concerns;

/**
 * Input uang di form diketik dengan titik ribuan ("9.965.000") dan qty boleh memakai koma ("2,5").
 * Trait ini mengubahnya menjadi angka murni sebelum divalidasi.
 */
trait NormalisasiAngka
{
    /** "Rp 9.965.000" -> "9965000", "1.500,5" -> "1500.5" */
    protected function uang(mixed $nilai): mixed
    {
        if (! is_string($nilai) || trim($nilai) === '') {
            return $nilai;
        }

        return str_replace(',', '.', str_replace(['Rp', ' ', '.'], '', $nilai));
    }

    /** Qty: koma sebagai pemisah desimal ("2,5" -> "2.5"). */
    protected function desimal(mixed $nilai): mixed
    {
        return is_string($nilai) ? str_replace(',', '.', trim($nilai)) : $nilai;
    }

    /** Normalisasi beberapa field uang sekaligus (hanya yang dikirim). */
    protected function normalisasiUang(array $fields): void
    {
        $this->merge(collect($fields)
            ->filter(fn ($f) => $this->has($f))
            ->mapWithKeys(fn ($f) => [$f => $this->uang($this->input($f))])
            ->all());
    }
}
