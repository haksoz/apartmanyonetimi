<?php

namespace App\Http\Middleware;

use App\Support\CurrentApartment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionFeature
{
    public function __construct(private CurrentApartment $currentApartment) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        $apartment = $this->currentApartment->getFor($user);

        if (! $apartment) {
            return $next($request);
        }

        if (! \App\Support\FeatureGate::allows($apartment, $feature, $user)) {
            abort(403, 'Bu özellik ücretli plandadır.');
        }

        return $next($request);
    }
}
