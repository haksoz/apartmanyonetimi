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
        $needsMembership = $consent->needsMembership($user);
        $needsPrivacy = $consent->needsPrivacy($user);

        if (! $needsMembership && ! $needsPrivacy) {
            return redirect()->back();
        }

        $rules = [];
        $messages = [];

        if ($needsMembership) {
            $rules['accept_membership'] = ['accepted'];
            $messages['accept_membership.accepted'] = 'Devam etmek için üyelik sözleşmesini kabul edin.';
        }

        if ($needsPrivacy) {
            $rules['accept_privacy'] = ['accepted'];
            $messages['accept_privacy.accepted'] = 'Devam etmek için gizlilik ve KVKK aydınlatmasını kabul edin.';
        }

        $request->validate($rules, $messages);

        if ($needsMembership) {
            $consent->recordMembership($user, $request);
        }

        if ($needsPrivacy) {
            $consent->recordPrivacy($user, $request);
        }

        return redirect()
            ->back()
            ->with('status', 'Onayınız kaydedildi.');
    }
}
