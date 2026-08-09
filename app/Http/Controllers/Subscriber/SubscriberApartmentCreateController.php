<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Unit;
use App\Support\CurrentApartment;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriberApartmentCreateController extends Controller
{
    public function create()
    {
        $user = auth()->user();
        $subscription = $user->subscription;

        // Check if user has an active subscription
        if (!$subscription || $subscription->isExpired()) {
            return redirect()->route('landing')->with('error', 'Apartman oluşturmak için aktif bir aboneliğiniz olmalıdır.');
        }

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

        if (! app(UserApartmentQuota::class)->canCreate($user)) {
            return back()->withErrors([
                'quota' => 'Mevcut paketinizin apartman limitine ulaştınız. Daha fazla apartman eklemek için paketinizi yükseltin veya yönetici ile iletişime geçin.',
            ])->withInput();
        }

        $apartment = DB::transaction(function () use ($validated, $user) {
            $apartment = Apartment::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'province' => $validated['province'] ?? null,
                'district' => $validated['district'] ?? null,
                'unit_count' => $validated['unit_count'],
            ]);

            $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
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

        return redirect()->route('apartments.wizard.cash-box', $apartment)
            ->with('status', 'Apartman oluşturuldu. Şimdi kasanızı oluşturun.');
    }
}
