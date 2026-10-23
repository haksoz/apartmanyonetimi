<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class ApartmentSelectionController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('subscriber.dashboard');
    }
}
