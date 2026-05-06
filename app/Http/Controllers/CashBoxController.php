<?php

namespace App\Http\Controllers;

use App\Models\CashBox;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class CashBoxController extends Controller
{
    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return view('cash.boxes.create', compact('apartment'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $validated = $this->validateCashBox($request);

        CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'iban' => $validated['iban'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('cash.index')->with('status', 'Kasa oluşturuldu.');
    }

    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $cashBox = $this->findCashBox($id, $currentApartment);

        if ($cashBox instanceof \Illuminate\Http\RedirectResponse) {
            return $cashBox;
        }

        return view('cash.boxes.edit', compact('cashBox'));
    }

    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $cashBox = $this->findCashBox($id, $currentApartment);

        if ($cashBox instanceof \Illuminate\Http\RedirectResponse) {
            return $cashBox;
        }

        $validated = $this->validateCashBox($request);

        $cashBox->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'iban' => $validated['iban'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('cash.index')->with('status', 'Kasa güncellendi.');
    }

    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $cashBox = $this->findCashBox($id, $currentApartment);

        if ($cashBox instanceof \Illuminate\Http\RedirectResponse) {
            return $cashBox;
        }

        $cashBox->delete();

        return redirect()->route('cash.index')->with('status', 'Kasa silindi.');
    }

    private function resolveApartment(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        return $apartment;
    }

    private function findCashBox(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);

        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        return CashBox::query()
            ->where('apartment_id', $apartment->id)
            ->findOrFail($id);
    }

    private function validateCashBox(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
