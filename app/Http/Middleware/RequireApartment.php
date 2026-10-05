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

        // Apartmanı yoksa uygun panele yönlendir
        if (! $currentApartment->hasAvailableFor($user)) {
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            if ($user->isSubscriber()) {
                return redirect()->route('subscriber.dashboard');
            }

            return redirect()->route('onboarding.show');
        }

        $apartment = $currentApartment->getFor($user);

        if ($user->isAdmin()) {
            $isOwnerOfCurrent = true;
        } elseif ($apartment) {
            $member = $apartment->members()->withPivot('role')->whereKey($user->id)->first();
            $isOwnerOfCurrent = $member && $member->pivot->role === 'owner';
        } else {
            // Henüz apartman seçilmemis; herhangi bir apartmanda owner mi?
            $isOwnerOfCurrent = $user->apartments()
                ->wherePivot('role', 'owner')
                ->wherePivot('is_active', true)
                ->exists();
        }

        view()->share('navIsOwner', $isOwnerOfCurrent);

        return $next($request);
    }
}
