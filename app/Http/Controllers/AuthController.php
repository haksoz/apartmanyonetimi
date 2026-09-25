<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSubscription;
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

        $request->session()->regenerate();

        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($request->user()->isSubscriber()) {
            return redirect()->route('subscriber.dashboard');
        }

        return redirect()->route('onboarding.show');
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

        // Get trial package from system settings
        $package = SystemSetting::getTrialPackage();
        $trialDuration = SystemSetting::getTrialDuration();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'manager',
        ]);

        // Get package features
        $featureAutoDues = $package->features->where('feature_key', 'Otomatik aidat planlama')->first()?->is_enabled ?? false;
        $featureUserPortal = $package->features->where('feature_key', 'Kullanıcı portalı erişimi')->first()?->is_enabled ?? false;
        $featureReports = $package->features->where('feature_key', 'Hesap ekstresi ve raporlar')->first()?->is_enabled ?? false;
        $featureMultiApartment = $package->features->where('feature_key', 'Çoklu apartman yönetimi')->first()?->is_enabled ?? false;

        // Free trial for configured duration
        UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'period' => 'monthly',
            'price' => 0,
            'started_at' => now(),
            'expires_at' => now()->addMonths($trialDuration),
            'is_active' => true,
            'is_trial' => true,
            'feature_auto_dues' => $featureAutoDues,
            'feature_user_portal' => $featureUserPortal,
            'feature_reports' => $featureReports,
            'feature_multi_apartment' => $featureMultiApartment,
            'multi_apartment_limit_override' => $featureMultiApartment ? $package->multi_apartment_limit : null,
        ]);

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
