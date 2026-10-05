<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function show(CurrentApartment $currentApartment)
    {
        if ($currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('dashboard');
        }

        if ($currentApartment->isSuspendedFor(auth()->user())) {
            return view('suspended');
        }

        return view('onboarding.setup');
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        if ($currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'address'          => ['nullable', 'string'],
            'unit_count'       => ['required', 'integer', 'min:1', 'max:500'],
            'manager_type'     => ['required', 'in:external,owner,tenant'],
            'manager_unit_no'  => ['required_if:manager_type,owner,tenant', 'nullable', 'integer', 'min:1'],
        ]);

        $user = auth()->user();
        $decision = app(\App\Support\ApartmentCommercial::class)->assess((int) $validated['unit_count'], $request->boolean('wants_paid'));

        if (! $decision['allowed']) {
            return back()->withErrors(['unit_count' => $decision['message']])->withInput();
        }

        $apartment = DB::transaction(function () use ($validated, $user, $currentApartment, $decision) {
            $apartment = \App\Models\Apartment::create([
                'user_id'    => $user->id,
                'name'       => $validated['name'],
                'address'    => $validated['address'] ?? null,
                'unit_count' => $validated['unit_count'],
                'billing_plan' => $decision['plan'],
            ]);

            $apartment->members()->attach($user->id, ['role' => 'owner']);
            app(\App\Support\SubscriptionCheckout::class)->openFree($user, $apartment);
            Category::createDefaultsFor($apartment->id);

            $managerUnitId = null;
            $managerAccount = null;

            for ($i = 1; $i <= $validated['unit_count']; $i++) {
                $unitNo = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

                $unit = Unit::create([
                    'apartment_id' => $apartment->id,
                    'unit_no'      => $unitNo,
                ]);

                // Boş kat maliki hesabı oluştur (user_id = null, bilgiler boş)
                $ownerAccount = Account::create([
                    'apartment_id' => $apartment->id,
                    'unit_id'      => $unit->id,
                    'type'         => Account::TYPE_OWNER,
                    'name'         => $unitNo.'. Daire Kat Maliki',
                    'user_id'      => null, // Başlangıçta user yok
                ]);

                $unit->update([
                    'owner_account_id'    => $ownerAccount->id,
                    'occupant_account_id' => $ownerAccount->id,
                ]);

                if ((int) ($validated['manager_unit_no'] ?? 0) === $i) {
                    $managerUnitId = $unit->id;

                    if ($validated['manager_type'] === 'owner') {
                        // Yönetici kendi hesabını bağla
                        $ownerAccount->update(['user_id' => $user->id]);
                        $ownerAccount->update([
                            'name' => $user->name,
                            'phone' => $user->phone ?? null,
                        ]);
                        $managerAccount = $ownerAccount;
                    }
                }
            }

            if ($validated['manager_type'] === 'tenant' && $managerUnitId) {
                $tenantAccount = Account::create([
                    'apartment_id' => $apartment->id,
                    'unit_id'      => $managerUnitId,
                    'user_id'      => $user->id,
                    'type'         => Account::TYPE_TENANT,
                    'name'         => $user->name,
                ]);

                $managerUnit = Unit::find($managerUnitId);
                $managerUnit->update(['occupant_account_id' => $tenantAccount->id]);
                $managerAccount = $tenantAccount;
            }

            if ($managerUnitId) {
                $apartment->update(['manager_unit_id' => $managerUnitId]);
            }

            $currentApartment->setFor($user, $apartment->id);

            return $apartment;
        });

        if ($decision['plan'] === 'paid' && $user->isSubscriber()) {
            $subscription = app(\App\Support\SubscriptionCheckout::class)->openPending($user, collect([$apartment]), 'monthly', 'havale');

            return redirect()->route('subscriber.subscriptions.receipt', $subscription)
                ->with('status', 'Apartmanınız oluşturuldu. Ücretli kullanım için ödemenizi tamamlayın.');
        }

        return redirect()->route('apartments.wizard.cash-box', $apartment)
            ->with('status', 'Apartmanınız oluşturuldu. Şimdi kasanızı oluşturun.');
    }
}
