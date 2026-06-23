<?php

namespace App\Http\Controllers;

use App\Models\Package;
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

    public function showRegister()
    {
        $packages = Package::where('is_active', true)->orderBy('sort_order')->get();

        return view('auth.register', compact('packages'));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'package_id' => ['required', 'exists:packages,id'],
            'period' => ['required', 'in:monthly,yearly'],
        ]);

        $package = Package::findOrFail($validated['package_id']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'manager',
        ]);

        UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'period' => $validated['period'],
            'price' => $validated['period'] === 'yearly' ? $package->yearly_price : $package->monthly_price,
            'started_at' => now(),
            'expires_at' => $validated['period'] === 'yearly' ? now()->addYear() : now()->addMonth(),
            'is_active' => true,
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
}
