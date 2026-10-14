<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Support\LegalConsent;
use App\Support\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Giriş bilgileri hatalı.',
            ])->onlyInput('email');
        }

        if ($request->user()->deactivated_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Bu hesap pasif.',
            ])->onlyInput('email');
        }

        if (! $request->user()->isActivated()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Bu hesap henüz daveti kabul etmedi. Davet bağlantısını kullanın.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return app(AccountEntry::class)->redirect($request->user());
    }

    public function showRegister($package = null)
    {
        if (! session()->has('register_challenge')) {
            $this->refreshHumanChallenge();
        }

        $challenge = session('register_challenge');

        return view('auth.register', [
            'challengeA' => $challenge['a'],
            'challengeB' => $challenge['b'],
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'human_answer' => ['required', 'integer'],
            'company' => ['prohibited'],
            'accept_membership' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], [
            'accept_membership.accepted' => 'Devam etmek için üyelik sözleşmesini kabul edin.',
            'accept_privacy.accepted' => 'Devam etmek için gizlilik ve KVKK aydınlatmasını kabul edin.',
        ]);

        $challenge = session('register_challenge');
        $openedAt = (int) ($challenge['opened_at'] ?? 0);

        if ($request->filled('company') || ! is_array($challenge)) {
            $this->refreshHumanChallenge();

            return back()->withErrors([
                'human_answer' => 'Kayıt doğrulanamadı. Lütfen tekrar deneyin.',
            ])->onlyInput('name', 'email');
        }

        if (now()->timestamp - $openedAt < 3) {
            return back()->withErrors([
                'human_answer' => 'Form çok hızlı gönderildi. Lütfen güvenlik sorusunu yanıtlayıp tekrar deneyin.',
            ])->onlyInput('name', 'email');
        }

        if ((int) $validated['human_answer'] !== (int) $challenge['sum']) {
            $this->refreshHumanChallenge();

            return back()->withErrors([
                'human_answer' => 'Güvenlik sorusu hatalı. Yeni soruyu yanıtlayın.',
            ])->onlyInput('name', 'email');
        }

        session()->forget('register_challenge');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'manager',
            'activated_at' => now(),
        ]);

        UserQuotaOverride::query()->create([
            'user_id' => $user->id,
            'max_apartments' => 1,
        ]);

        app(LegalConsent::class)->recordMembership($user, $request);
        app(LegalConsent::class)->recordPrivacy($user, $request);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('subscriber.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function refreshHumanChallenge(): void
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);

        session([
            'register_challenge' => [
                'a' => $a,
                'b' => $b,
                'sum' => $a + $b,
                'opened_at' => now()->timestamp,
            ],
        ]);
    }
}
