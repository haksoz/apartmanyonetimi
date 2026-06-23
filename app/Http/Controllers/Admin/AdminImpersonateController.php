<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminImpersonateController extends Controller
{
    public function start(Request $request, User $manager)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($manager->isAdmin(), 403, 'Admin kullanıcıya geçiş yapılamaz.');

        $request->session()->put('impersonate_admin_id', $request->user()->id);
        $request->session()->put('impersonate_user_id', $manager->id);

        Auth::loginUsingId($manager->id);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', $manager->name.' olarak giriş yapıldı.');
    }

    public function leave(Request $request)
    {
        $adminId = $request->session()->get('impersonate_admin_id');

        if (! $adminId) {
            return redirect()->route('dashboard');
        }

        $request->session()->forget(['impersonate_admin_id', 'impersonate_user_id']);

        Auth::loginUsingId($adminId);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('status', 'Admin oturumuna geri dönüldü.');
    }
}
