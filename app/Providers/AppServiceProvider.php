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

            if (auth()->check()) {
                $currentApartmentService = app(CurrentApartment::class);
                $availableApartments = $currentApartmentService->availableFor(auth()->user());
                $currentApartment = $currentApartmentService->getFor(auth()->user());
            }

            $view->with(compact('availableApartments', 'currentApartment'));
        });
    }
}
