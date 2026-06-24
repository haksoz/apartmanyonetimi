<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Unit;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $currentApartment = session('current_apartment_id')
            ? Apartment::with(['units', 'accounts.unit'])->findOrFail(session('current_apartment_id'))
            : null;

        if (! $currentApartment) {
            return redirect()->route('current-apartment.select');
        }

        $isOwner = $this->isOwnerOf($currentApartment);

        $hasImported = AccountTransaction::where('apartment_id', $currentApartment->id)
            ->where('is_imported', true)
            ->exists();

        return view('apartments.show', [
            'apartment' => $currentApartment,
            'isOwner' => $isOwner,
            'hasImported' => $hasImported,
        ]);
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
            'address' => ['required', 'string'],
            'unit_count' => ['required', 'integer', 'min:1', 'max:500'],
            'account_opening_date' => ['required', 'date'],
        ]);

        $user = auth()->user();

        if (! app(UserApartmentQuota::class)->canCreate($user)) {
            return back()->withErrors([
                'quota' => 'Mevcut paketinizin apartman limitine ulaştınız. Daha fazla apartman eklemek için paketinizi yükseltin veya yönetici ile iletişime geçin.',
            ])->withInput();
        }

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
            }
        });

        $redirectRoute = auth()->user()->isSubscriber() ? 'subscriber.apartments.index' : 'apartments.index';
        return redirect()->route($redirectRoute)->with('status', 'Apartman ve daire hesapları oluşturuldu.');
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

        $isOwner = $this->isOwnerOf($apartment);

        $hasImported = AccountTransaction::where('apartment_id', $apartment->id)
            ->where('is_imported', true)
            ->exists();

        return view('apartments.show', compact('apartment', 'isOwner', 'hasImported'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $apartment = Apartment::query()
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->findOrFail($id);

        return view('apartments.edit', compact('apartment'));
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
            'name'    => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
        ]);

        $apartment->update([
            'name'    => $validated['name'],
            'address' => $validated['address'],
        ]);

        $redirectRoute = auth()->user()->isSubscriber() ? 'subscriber.apartments.index' : 'apartments.show';
        return redirect()->route($redirectRoute, $apartment)->with('status', 'Apartman bilgileri güncellendi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $apartment = Apartment::findOrFail($id);
        $apartment->delete();

        return redirect()->route('apartments.index')->with('status', 'Apartman silindi.');
    }
}
