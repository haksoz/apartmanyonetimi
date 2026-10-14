<?php

namespace App\Http\Controllers;

use App\Support\AccountEntry;
use App\Support\LegalConsent;
use Illuminate\Http\Request;

class MembershipConsentController extends Controller
{
    public function show(LegalConsent $consent)
    {
        $user = auth()->user();

        if (! $consent->needsMembership($user)) {
            return app(AccountEntry::class)->redirect($user);
        }

        return view('auth.membership-consent');
    }

    public function store(Request $request, LegalConsent $consent)
    {
        $user = $request->user();

        if (! $consent->needsMembership($user)) {
            return app(AccountEntry::class)->redirect($user);
        }

        $request->validate([
            'accept_membership' => ['accepted'],
        ], [
            'accept_membership.accepted' => 'Devam etmek için üyelik sözleşmesini kabul edin.',
        ]);

        $consent->recordMembership($user, $request);

        return app(AccountEntry::class)->redirect($user->fresh());
    }
}
