<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Support\BuildingPlaces;
use App\Support\CurrentApartment;
use App\Support\PriceQuote;
use App\Support\SubscriberApartmentOverview;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;

class SubscriberDashboardController extends Controller
{
    public function __invoke(Request $request, CurrentApartment $currentApartment, PriceQuote $prices, SubscriberApartmentOverview $overview, BuildingPlaces $places)
    {
        $user = auth()->user();
        $managedApartments = $places->grouped($user)['managed'];
        $currentApartmentModel = $currentApartment->getFor($user);

        if ($currentApartmentModel
            && $managedApartments->contains('id', $currentApartmentModel->id)
            && ($nextStep = $currentApartmentModel->nextSetupStep())) {
            return redirect()->route('apartments.wizard.'.$nextStep, $currentApartmentModel)
                ->with('status', 'Lütfen apartman kurulumunu tamamlayın.');
        }

        $overview->decorate($managedApartments, $user, $prices);

        $residenceAccounts = Account::query()
            ->with(['apartment', 'unit'])
            ->where('user_id', $user->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->whereHas('apartment', function ($query) use ($user) {
                $query->where('is_active', true)
                    ->whereHas('members', function ($members) use ($user) {
                        $members->whereKey($user->id)->where('apartment_user.is_active', true);
                    });
            })
            ->orderBy('apartment_id')
            ->orderBy('id')
            ->get();

        $quota = app(UserApartmentQuota::class);
        $apartmentLimit = $quota->definedLimit($user);
        $apartmentCount = $apartmentLimit === null ? null : $quota->currentCount($user);
        $canCreateApartment = $quota->canCreate($user);
        $showApartmentCreate = $managedApartments->isNotEmpty()
            || ($residenceAccounts->isEmpty() && $user->isSubscriber() && $canCreateApartment);

        return view('subscriber.dashboard', [
            'apartments' => $managedApartments,
            'residenceAccounts' => $residenceAccounts,
            'currentApartmentModel' => $currentApartmentModel,
            'apartmentLimit' => $apartmentLimit,
            'apartmentCount' => $apartmentCount,
            'canCreateApartment' => $canCreateApartment,
            'showApartmentCreate' => $showApartmentCreate,
        ]);
    }
}
