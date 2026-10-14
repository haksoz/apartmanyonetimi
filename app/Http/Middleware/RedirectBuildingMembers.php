<?php

namespace App\Http\Middleware;

use App\Support\CurrentApartment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectBuildingMembers
{
    public function __construct(private CurrentApartment $currentApartment) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        $apartment = $this->currentApartment->getFor($user);

        if (! $apartment) {
            return $next($request);
        }

        $isOwner = $apartment->members()
            ->whereKey($user->id)
            ->where('apartment_user.role', 'owner')
            ->where('apartment_user.is_active', true)
            ->exists();

        if ($isOwner) {
            return $next($request);
        }

        return redirect()->route('residence.home');
    }
}
