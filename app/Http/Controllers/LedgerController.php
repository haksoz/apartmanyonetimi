<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Support\CurrentApartment;

class LedgerController extends Controller
{
    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $transactions = AccountTransaction::query()
            ->with(['account', 'transactionable'])
            ->when($apartment, fn ($query) => $query->whereHas('account', fn ($query) => $query->where('apartment_id', $apartment->id)))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('ledger.index', compact('transactions'));
    }
}
