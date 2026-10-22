<?php

namespace App\Providers;

use App\Models\AccountPortalOffer;
use App\Models\ManagementOffer;
use App\Support\ApartmentReset\ApartmentResetArchive;
use App\Support\LegalConsent;
use App\Support\ApartmentReset\UnavailableApartmentResetArchive;
use App\Support\BuildingPlaces;
use App\Support\CurrentApartment;
use App\Support\SubscriberMenu;
use App\Support\UserApartmentQuota;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ApartmentResetArchive::class, UnavailableApartmentResetArchive::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.app', function ($view) {
            $availableApartments = collect();
            $currentApartment = null;
            $navIsOwner = false;
            $showResidence = false;
            $homeUrl = route('dashboard');
            $managementOffers = collect();
            $needsMembershipConsent = false;
            $needsPrivacyConsent = false;
            $portalOffer = null;
            $showSubscriberCommerce = false;

            if (auth()->check()) {
                $user = auth()->user();
                $currentApartmentService = app(CurrentApartment::class);
                $availableApartments = $currentApartmentService->availableFor($user);
                $currentApartment = $currentApartmentService->getFor($user);

                if ($user->isAdmin()) {
                    $navIsOwner = true;
                } elseif ($currentApartment) {
                    $member = $currentApartment->members()
                        ->withPivot('role')
                        ->whereKey($user->id)
                        ->first();
                    $navIsOwner = $member && $member->pivot->role === 'owner';
                } else {
                    $navIsOwner = $user->apartments()
                        ->wherePivot('role', 'owner')
                        ->wherePivot('is_active', true)
                        ->exists();
                }

                $showResidence = $currentApartment
                    && ! $user->isAdmin()
                    && (! $navIsOwner || app(BuildingPlaces::class)->hasResidenceIn($user, $currentApartment));

                $homeUrl = $user->isAdmin()
                    ? route('admin.dashboard')
                    : route('subscriber.dashboard');
                $showSubscriberCommerce = app(SubscriberMenu::class)->showsCommerce($user);

                $managementOffers = ManagementOffer::query()
                    ->with('apartment:id,name')
                    ->where('user_id', $user->id)
                    ->where(function ($query) {
                        $query->openPending()
                            ->orWhere('status', ManagementOffer::STATUS_BLOCKED);
                    })
                    ->get();

                if (app(UserApartmentQuota::class)->hasRoomForManagement($user)) {
                    $managementOffers = $managementOffers
                        ->reject(fn (ManagementOffer $offer) => $offer->status === ManagementOffer::STATUS_BLOCKED)
                        ->values();
                }

                if (! session()->has('impersonate_admin_id')) {
                    $portalOffer = AccountPortalOffer::query()
                        ->with(['apartment:id,name', 'account.unit', 'offerer:id,name'])
                        ->where('user_id', $user->id)
                        ->where('status', AccountPortalOffer::STATUS_PENDING)
                        ->latest()
                        ->first();
                }

                if (! $user->isAdminPanelUser() && ! session()->has('impersonate_admin_id')) {
                    $consent = app(LegalConsent::class);
                    $needsMembershipConsent = $consent->needsMembership($user);
                    $needsPrivacyConsent = $consent->needsPrivacy($user);
                }
            }

            $view->with(compact(
                'availableApartments',
                'currentApartment',
                'navIsOwner',
                'showResidence',
                'homeUrl',
                'managementOffers',
                'needsMembershipConsent',
                'needsPrivacyConsent',
                'portalOffer',
                'showSubscriberCommerce',
            ));
        });
    }
}
