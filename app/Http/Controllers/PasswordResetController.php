<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user?->deactivated_at) {
            return back()
                ->withErrors(['email' => 'Bu hesap pasif.'])
                ->onlyInput('email');
        }

        if ($user && ! $user->isActivated()) {
            return back()
                ->withErrors(['email' => 'Bu hesap henüz daveti kabul etmedi. Davet bağlantısını kullanın.'])
                ->onlyInput('email');
        }

        if ($user) {
            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_THROTTLED) {
                return back()
                    ->withErrors(['email' => 'Yeniden denemeden önce biraz bekleyin.'])
                    ->onlyInput('email');
            }
        }

        return back()->with('status', 'Bu e-posta kayıtlıysa sıfırlama bağlantısı gönderildi.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                if ($user->deactivated_at || ! $user->isActivated()) {
                    throw ValidationException::withMessages([
                        'email' => 'Bu hesap şifre sıfırlayamaz.',
                    ]);
                }

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => 'Sıfırlama bağlantısı geçersiz veya süresi dolmuş.'])
                ->onlyInput('email');
        }

        $user = User::query()->where('email', $request->string('email')->toString())->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return app(AccountEntry::class)->redirect($user);
    }
}
