<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriberApartmentCreateController extends Controller
{
    public function create()
    {
        return view('apartments.create');
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'unit_count' => ['required', 'integer', 'min:1', 'max:500'],
            'account_opening_date' => ['required', 'date'],
        ]);

        $user = auth()->user();
        $decision = app(\App\Support\ApartmentCommercial::class)->assess((int) $validated['unit_count'], $request->boolean('wants_paid'));

        if (! $decision['allowed']) {
            return back()->withErrors(['unit_count' => $decision['message']])->withInput();
        }

        $apartment = DB::transaction(function () use ($validated, $user, $decision) {
            $apartment = Apartment::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'province' => $validated['province'] ?? null,
                'district' => $validated['district'] ?? null,
                'unit_count' => $validated['unit_count'],
                'billing_plan' => $decision['plan'],
            ]);

            $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
            app(\App\Support\SubscriptionCheckout::class)->openFree($user, $apartment);
            Category::createDefaultsFor($apartment->id);

            for ($i = 1; $i <= $validated['unit_count']; $i++) {
                $unitNo = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

                $unit = Unit::create([
                    'apartment_id' => $apartment->id,
                    'unit_no' => $unitNo,
                ]);

                $ownerAccount = Account::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $unit->id,
                    'type' => Account::TYPE_OWNER,
                    'name' => $unitNo.'. Daire Kat Maliki',
                    'account_opening_date' => $validated['account_opening_date'],
                ]);

                $unit->update([
                    'owner_account_id' => $ownerAccount->id,
                    'occupant_account_id' => $ownerAccount->id,
                ]);
            }

            return $apartment;
        });

        // Set the newly created apartment as current
        $currentApartment->setFor($user, $apartment->id);

        if ($decision['plan'] === 'paid') {
            $subscription = app(\App\Support\SubscriptionCheckout::class)->openPending($user, collect([$apartment]), 'monthly', 'havale');

            return redirect()->route('subscriber.subscriptions.receipt', $subscription)
                ->with('status', 'Apartman oluşturuldu. Ücretli kullanım için ödemenizi tamamlayın.');
        }

        return redirect()->route('apartments.wizard.cash-box', $apartment)
            ->with('status', 'Apartman oluşturuldu. Şimdi kasanızı oluşturun.');
    }
}
