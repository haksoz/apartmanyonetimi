<?php

namespace App\Http\Middleware;

use App\Support\LegalConsent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePromotedManagerMembership
{
    public function __construct(private LegalConsent $consent) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->session()->has('impersonate_admin_id')) {
            return $next($request);
        }

        if ($request->routeIs('legal.membership.accept', 'legal.membership.accept.store', 'logout')) {
            return $next($request);
        }

        if (! $this->consent->needsMembership($user)) {
            return $next($request);
        }

        return redirect()->route('legal.membership.accept');
    }
}
