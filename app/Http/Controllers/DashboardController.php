<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\Expense;
use App\Support\CurrentApartment;

class DashboardController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $apartmentIds = $apartment ? collect([$apartment->id]) : collect();

        $stats = [
            'apartments' => $apartment ? 1 : 0,
            'accounts' => Account::whereIn('apartment_id', $apartmentIds)->count(),
            'dues_total' => Due::whereIn('apartment_id', $apartmentIds)->sum('amount'),
            'expenses_total' => Expense::whereIn('apartment_id', $apartmentIds)->sum('amount'),
            'cash_balance' => CashTransaction::whereIn('apartment_id', $apartmentIds)->where('type', 'income')->sum('amount') - CashTransaction::whereIn('apartment_id', $apartmentIds)->where('type', 'expense')->sum('amount'),
        ];

        return view('dashboard', compact('apartment', 'stats'));
    }
}
