<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ],
            [
                'current_password.required' => '現在のパスワードを入力してください。',
                'current_password.current_password' => '現在のパスワードが正しくありません。',
                'password.required' => '新しいパスワードを入力してください。',
                'password.confirmed' => '新しいパスワードが一致しません。',
            ],
        );

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return redirect()->route('home');
    }
}
