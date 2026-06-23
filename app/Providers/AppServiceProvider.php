<?php

namespace App\Providers;

use App\Support\CurrentApartment;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $availableApartments = collect();
            $currentApartment = null;
            $navIsOwner = false;

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
            }

            $view->with(compact('availableApartments', 'currentApartment', 'navIsOwner'));
        });
    }
}
