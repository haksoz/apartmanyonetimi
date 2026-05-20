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

        DB::transaction(function () use ($validated, $user, $currentApartment) {
            $apartment = \App\Models\Apartment::create([
                'user_id'    => $user->id,
                'name'       => $validated['name'],
                'address'    => $validated['address'] ?? null,
                'unit_count' => $validated['unit_count'],
            ]);

            $apartment->members()->attach($user->id, ['role' => 'owner']);
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
        });

        return redirect()->route('dashboard')->with('status', 'Apartmanınız oluşturuldu. Hoş geldiniz!');
    }
}
