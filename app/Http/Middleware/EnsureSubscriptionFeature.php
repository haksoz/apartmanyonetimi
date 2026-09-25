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

        $owner = $apartment->user;

        if (! $owner || ! $owner->hasFeature($feature)) {
            abort(403, 'Bu özellik aboneliğinizde aktif değil. Erişim için yöneticinizle iletişime geçin.');
        }

        return $next($request);
    }
}
