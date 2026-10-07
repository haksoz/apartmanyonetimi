<?php

namespace App\Http\Controllers;

use App\Models\PriceBand;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('landing');
    }

    public function pricing()
    {
        return view('microsite.pricing', [
            'priceBands' => $this->activePriceBands(),
        ]);
    }

    private function activePriceBands()
    {
        $now = now();

        return PriceBand::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->orderBy('sort_order')
            ->orderBy('min_units')
            ->get();
    }
}
