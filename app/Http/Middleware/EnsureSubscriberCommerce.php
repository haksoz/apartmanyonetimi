<?php

namespace App\Http\Middleware;

use App\Support\SubscriberMenu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriberCommerce
{
    public function __construct(private SubscriberMenu $menu) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->menu->showsCommerce($user)) {
            abort(403, 'Bu alana erişim yetkiniz yok.');
        }

        return $next($request);
    }
}
