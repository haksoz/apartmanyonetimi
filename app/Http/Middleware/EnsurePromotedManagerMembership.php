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

        if ($request->routeIs(
            'legal.membership.accept',
            'legal.membership.accept.store',
            'logout',
            'management-offers.show',
            'management-offers.accept',
            'management-offers.reject',
        )) {
            return $next($request);
        }

        if (! $this->consent->needsMembership($user) || $request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        return redirect()
            ->back()
            ->withErrors(['accept_membership' => 'Devam etmek için üyelik sözleşmesini kabul edin.']);
    }
}
