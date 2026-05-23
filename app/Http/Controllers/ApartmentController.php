<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $apartments = Apartment::query()
            ->withCount(['units', 'accounts'])
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->latest()
            ->get();

        return view('apartments.index', compact('apartments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('apartments.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'unit_count' => ['required', 'integer', 'min:1', 'max:500'],
            'manager_unit_no' => ['nullable', 'integer', 'min:1'],
            'account_opening_date' => ['required', 'date'],
        ]);

        $user = auth()->user();

        DB::transaction(function () use ($validated, $user) {
            $apartment = Apartment::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'unit_count' => $validated['unit_count'],
            ]);

            $apartment->members()->attach($user->id, ['role' => 'owner']);
            Category::createDefaultsFor($apartment->id);

            for ($i = 1; $i <= $validated['unit_count']; $i++) {
                $unitNo = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

                $unit = Unit::create([
                    'apartment_id' => $apartment->id,
                    'unit_no' => $unitNo,
                ]);

                $ownerAccount = Account::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $unit->id,
                    'type' => Account::TYPE_OWNER,
                    'name' => $unitNo.'. Daire Kat Maliki',
                    'account_opening_date' => $validated['account_opening_date'],
                ]);

                $unit->update([
                    'owner_account_id' => $ownerAccount->id,
                    'occupant_account_id' => $ownerAccount->id,
                ]);

                if ((int) ($validated['manager_unit_no'] ?? 0) === $i) {
                    $apartment->update(['manager_unit_id' => $unit->id]);
                }
            }
        });

        return redirect()->route('apartments.index')->with('status', 'Apartman ve daire hesapları oluşturuldu.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $apartment = Apartment::query()
            ->with(['units', 'accounts.unit'])
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->findOrFail($id);

        return view('apartments.show', compact('apartment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $apartment = Apartment::query()
            ->with(['units.accounts'])
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->findOrFail($id);

        $accounts = Account::query()
            ->with('unit')
            ->where('apartment_id', $apartment->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->orderByRaw('unit_id IS NULL, unit_id')
            ->orderBy('name')
            ->get();

        return view('apartments.edit', compact('apartment', 'accounts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $apartment = Apartment::query()
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->findOrFail($id);

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'address'            => ['nullable', 'string'],
            'manager_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $managerUnitId = null;
        if (! empty($validated['manager_account_id'])) {
            $managerAccount = Account::where('apartment_id', $apartment->id)
                ->findOrFail($validated['manager_account_id']);
            $managerUnitId = $managerAccount->unit_id;
        }

        $apartment->update([
            'name'             => $validated['name'],
            'address'          => $validated['address'] ?? null,
            'manager_unit_id'  => $managerUnitId,
        ]);

        return redirect()->route('apartments.show', $apartment)->with('status', 'Apartman bilgileri güncellendi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
