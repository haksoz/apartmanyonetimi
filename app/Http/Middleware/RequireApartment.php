<?php

namespace App\Http\Middleware;

use App\Support\CurrentApartment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApartment
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentApartment = app(CurrentApartment::class);
        $user = $request->user();

        // Onboarding sayfalarına erişime izin ver (sonsuz döngü olmaması için)
        if ($request->routeIs('onboarding.*')) {
            return $next($request);
        }

        // Apartmanı yoksa onboarding'e yönlendir
        if (! $currentApartment->hasAvailableFor($user)) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
