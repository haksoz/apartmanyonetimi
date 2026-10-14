<?php

namespace App\Providers;

use App\Support\ApartmentReset\ApartmentResetArchive;
use App\Support\ApartmentReset\UnavailableApartmentResetArchive;
use App\Support\BuildingPlaces;
use App\Support\CurrentApartment;
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

                $homeUrl = match (true) {
                    $user->isAdmin() => route('admin.dashboard'),
                    $user->isSubscriber() => route('subscriber.dashboard'),
                    $navIsOwner => route('dashboard'),
                    default => route('residence.home'),
                };
            }

            $view->with(compact('availableApartments', 'currentApartment', 'navIsOwner', 'showResidence', 'homeUrl'));
        });
    }
}
