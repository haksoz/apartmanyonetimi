<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartment;

class AdminArchivedApartmentController extends Controller
{
    public function index()
    {
        $apartments = Apartment::query()
            ->where('is_active', false)
            ->with('user')
            ->withCount(['units', 'accounts'])
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.apartments.archived', compact('apartments'));
    }
}
