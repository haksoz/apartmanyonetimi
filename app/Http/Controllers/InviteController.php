<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\AccountEntry;
use App\Support\LegalConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InviteController extends Controller
{
    public function show(string $token)
    {
        $invitation = UserInvitation::findValid($token);
        $existingUser = $invitation?->account_id
            ? User::query()->where('email', $invitation->email)->first()
            : $invitation?->user;
        $requiresLogin = $invitation?->account_id && $existingUser?->isActivated();

        return view('auth.invite', [
            'token' => $token,
            'invitation' => $invitation,
            'requiresLogin' => $requiresLogin,
            'canAccept' => ! $requiresLogin || auth()->id() === $existingUser->id,
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

        $existingUser = $invitation->account_id
            ? User::query()->where('email', $invitation->email)->first()
            : $invitation->user;
        $requiresLogin = $invitation->account_id && $existingUser?->isActivated();
        if ($requiresLogin && auth()->id() !== $existingUser->id) {
            return back()->withErrors(['token' => 'Bu e-posta ile oturum açıp daveti tekrar açın.']);
        }

        $validated = $request->validate([
            'password' => $requiresLogin ? ['nullable'] : ['required', 'string', 'min:8', 'confirmed'],
            'accept_privacy' => ['accepted'],
        ], [
            'accept_privacy.accepted' => 'Devam etmek için gizlilik ve KVKK aydınlatmasını kabul edin.',
        ]);

        $user = DB::transaction(function () use ($invitation, $token, $validated, $request) {
            $account = $invitation->account_id
                ? Account::query()->lockForUpdate()->find($invitation->account_id)
                : null;
            $locked = UserInvitation::query()->lockForUpdate()->find($invitation->id);
            if (! $locked || ! UserInvitation::findValid($token)) {
                throw ValidationException::withMessages(['token' => 'Davet bağlantısı geçersiz veya süresi dolmuş.']);
            }

            $user = $account
                ? User::query()->where('email', $locked->email)->lockForUpdate()->first()
                : $locked->user;
            if ($user?->deactivated_at) {
                throw ValidationException::withMessages(['token' => 'Bu hesap pasif.']);
            }
            if ($user?->isActivated()) {
                if (! $account || auth()->id() !== $user->id) {
                    throw ValidationException::withMessages(['token' => 'Bu e-posta ile oturum açıp daveti tekrar açın.']);
                }
            } else {
                if (empty($validated['password'])) {
                    throw ValidationException::withMessages(['password' => 'Parolanızı belirleyin.']);
                }
                $user ??= new User([
                    'name' => $account->name,
                    'email' => $locked->email,
                    'phone' => $account->phone,
                    'role' => 'user',
                ]);
                $user->fill(['password' => $validated['password'], 'activated_at' => now()])->save();
            }

            if ($account) {
                $account->update(['user_id' => $user->id]);
                if (! $account->apartment->members()->whereKey($user->id)->exists()) {
                    $account->apartment->members()->attach($user->id, ['role' => 'member', 'is_active' => true]);
                }
            }
            $locked->update(['user_id' => $user->id, 'accepted_at' => now()]);
            app(LegalConsent::class)->recordPrivacy($user, $request);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return app(AccountEntry::class)->redirect($user->fresh());
    }
}
