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
        return baca_uang($nilai);
    }

    /** Qty: koma sebagai pemisah desimal ("2,5" -> "2.5", "1.000,5" -> "1000.5"). */
    protected function desimal(mixed $nilai): mixed
    {
        return baca_qty($nilai);
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
