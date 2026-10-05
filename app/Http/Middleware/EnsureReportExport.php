<?php

namespace App\Http\Middleware;

use App\Support\CurrentApartment;
use App\Support\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReportExport
{
    public function __construct(private CurrentApartment $currentApartment) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        $apartment = $this->currentApartment->getFor($user);
        $type = (string) ($request->route('type') ?? $request->query('type', 'excel'));
        $key = $type === 'pdf' ? 'report_pdf' : 'report_excel';

        if (! FeatureGate::allows($apartment, $key, $user)) {
            abort(403, 'Bu özellik ücretli plandadır.');
        }

        return $next($request);
    }
}
