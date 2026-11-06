<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfilRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Pengaturan akun admin */
class UserController extends Controller
{
    public function edit(Request $request): View
    {
        return view('user.edit', ['user' => $request->user()]);
    }

    public function update(ProfilRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('user.edit')->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(PasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => Hash::make($request->password)]);

        return redirect()->route('user.edit')->with('success', 'Password berhasil diganti.');
    }
}
