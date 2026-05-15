<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $units = Unit::query()
            ->with(['ownerAccount', 'occupantAccount'])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->orderByRaw('CAST(unit_no AS UNSIGNED)')
            ->get();

        return view('units.index', compact('units', 'apartment'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        return view('units.create', compact('apartment'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $validated = $request->validate([
            'unit_no' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'block' => ['nullable', 'string', 'max:255'],
            'square_meters' => ['nullable', 'numeric', 'min:0'],
            'share_coefficient' => ['nullable', 'numeric', 'min:0'],
        ]);

        Unit::create([
            'apartment_id' => $apartment->id,
            ...$validated,
        ]);

        return redirect()->route('units.index')->with('status', 'Daire eklendi.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $unit = Unit::query()
            ->with([
                'ownerAccount',
                'occupantAccount',
                'ownerHistories.account',
                'tenantAssignments.account',
            ])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        return view('units.show', compact('unit', 'apartment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $unit = Unit::query()
            ->with(['ownerAccount', 'occupantAccount'])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        return view('units.edit', compact('unit', 'apartment'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $unit = Unit::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $validated = $request->validate([
            'unit_no' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'block' => ['nullable', 'string', 'max:255'],
            'square_meters' => ['nullable', 'numeric', 'min:0'],
            'share_coefficient' => ['nullable', 'numeric', 'min:0'],
        ]);

        $unit->update($validated);

        return redirect()->route('units.index')->with('status', 'Daire güncellendi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $unit = Unit::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        // Check if unit has accounts
        if ($unit->accounts()->exists()) {
            return back()->withErrors(['error' => 'Bu daireye bağlı hesaplar var. Önce hesapları silin veya başka daireye taşıyın.']);
        }

        $unit->delete();

        return redirect()->route('units.index')->with('status', 'Daire silindi.');
    }
}
