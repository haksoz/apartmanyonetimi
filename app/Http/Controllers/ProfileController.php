<?php

namespace App\Http\Controllers;

use App\Models\LegalAcceptance;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        $quota = app(UserApartmentQuota::class);
        $limit = $quota->definedLimit($user);
        $managed = $quota->currentCount($user);
        $remainingApartments = null;

        if (! $user->isAdmin() && ! $user->managesBuilding()) {
            $remainingApartments = max(0, ($limit ?? 0) - $managed);
        }

        return view('profile.edit', [
            'user' => $user,
            'remainingApartments' => $remainingApartments,
            'acceptances' => LegalAcceptance::query()
                ->where('user_id', $user->id)
                ->with(['apartment:id,name', 'order:id,order_number'])
                ->orderByDesc('accepted_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'regex:/^[1-9][0-9]{9}$/D'],
            'address' => ['required', 'string', 'max:400'],
        ], [
            'phone.regex' => 'Telefonu başında 0 olmadan 10 hane girin. Örnek: 5551234567.',
        ]);

        $request->user()->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'address' => $validated['address'],
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'İletişim bilgileriniz güncellendi.');
    }

    public function openApartment(Request $request)
    {
        if (! app(UserApartmentQuota::class)->canCreate($request->user())) {
            $request->session()->forget('open_apartment_requested');

            return redirect()->route('profile.edit')->with('status', UserApartmentQuota::CONTACT_MESSAGE);
        }

        $request->session()->put('open_apartment_requested', true);

        return redirect()->route('apartments.create');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Parola güncellendi.');
    }
}
