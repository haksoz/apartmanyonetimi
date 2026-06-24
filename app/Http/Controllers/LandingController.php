<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function __invoke(Request $request)
    {
        $packages = Package::where('is_active', true)
            ->orderBy('sort_order')
            ->with('features')
            ->get();

        return view('landing', compact('packages'));
    }
}
