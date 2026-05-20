<?php

namespace App\Http\Middleware;

use App\Support\CurrentApartment;
use Closure;
use Illuminate\Http\Request;

class EnsureOwner
{
    public function __construct(private CurrentApartment $currentApartment) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $next($request);
        }

        $apartment = $this->currentApartment->getFor($user);

        if (! $apartment) {
            abort(403);
        }

        $member = $apartment->members()
            ->withPivot('role')
            ->whereKey($user->id)
            ->first();

        if (! $member || $member->pivot->role !== 'owner') {
            abort(403, 'Bu işlem için yönetici yetkisi gereklidir.');
        }

        return $next($request);
    }
}
