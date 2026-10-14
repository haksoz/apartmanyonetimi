<?php

namespace App\Http\Controllers;

use App\Models\UserInvitation;
use App\Support\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InviteController extends Controller
{
    public function show(string $token)
    {
        $invitation = UserInvitation::findValid($token);

        return view('auth.invite', [
            'token' => $token,
            'invitation' => $invitation,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = UserInvitation::findValid($token);

        if (! $invitation) {
            return redirect()
                ->route('invite.show', $token)
                ->withErrors(['token' => 'Davet bağlantısı geçersiz veya süresi dolmuş.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $invitation->user;

        if ($user->deactivated_at) {
            return back()->withErrors(['token' => 'Bu hesap pasif.']);
        }

        $user->update([
            'password' => $validated['password'],
            'activated_at' => now(),
        ]);

        $invitation->update(['accepted_at' => now()]);

        Auth::login($user);
        $request->session()->regenerate();

        return app(AccountEntry::class)->redirect($user->fresh());
    }
}
