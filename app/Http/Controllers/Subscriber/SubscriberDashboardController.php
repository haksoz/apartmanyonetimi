<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\SubscriptionItem;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use App\Support\PriceQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SubscriberDashboardController extends Controller
{
    public function __invoke(Request $request, CurrentApartment $currentApartment, PriceQuote $prices)
    {
        $user = auth()->user();

        $apartments = $currentApartment->availableFor($user);
        $currentApartmentModel = $currentApartment->getFor($user);

        if ($currentApartmentModel && ($nextStep = $currentApartmentModel->nextSetupStep())) {
            return redirect()->route('apartments.wizard.'.$nextStep, $currentApartmentModel)
                ->with('status', 'Lütfen apartman kurulumunu tamamlayın.');
        }

        $coverage = SubscriptionItem::query()
            ->whereIn('apartment_id', $apartments->pluck('id'))
            ->with('subscription.user')
            ->orderByDesc('id')
            ->get()
            ->groupBy('apartment_id');

        $ownerIds = Apartment::query()
            ->whereIn('id', $apartments->pluck('id'))
            ->whereHas('members', function ($query) use ($user) {
                $query->whereKey($user->id)
                    ->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            })
            ->pluck('id');

        $apartments->each(function (Apartment $apartment) use ($coverage, $prices, $ownerIds) {
            $items = $coverage->get($apartment->id, collect());
            $apartment->setAttribute('commercial_active', $this->activeItem($items));
            $apartment->setAttribute('commercial_pending', $this->pendingItem($items));
            $apartment->setAttribute('can_purchase', $ownerIds->contains($apartment->id));
            $apartment->setAttribute('monthly_quote', $prices->forUnits((int) $apartment->unit_count, 'monthly', $apartment));
            $apartment->setAttribute('yearly_quote', $prices->forUnits((int) $apartment->unit_count, 'yearly', $apartment));
        });

        return view('subscriber.dashboard', compact('apartments', 'currentApartmentModel'));
    }

    private function activeItem(Collection $items): ?SubscriptionItem
    {
        return $items->first(function (SubscriptionItem $item) {
            $subscription = $item->subscription;

            return $item->isCovering();
        });
    }

    private function pendingItem(Collection $items): ?SubscriptionItem
    {
        return $items->first(function (SubscriptionItem $item) {
            return $item->subscription?->status === UserSubscription::STATUS_PENDING;
        });
    }
}
