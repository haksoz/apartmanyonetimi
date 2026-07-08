<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\CashBox;
use App\Models\Category;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\UnitOwnerHistory;
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
        $user = auth()->user();
        $subscription = $user->subscription;

        // Check if user has an active subscription
        if (!$subscription || $subscription->isExpired()) {
            return redirect()->route('landing')->with('error', 'Apartman oluşturmak için aktif bir aboneliğiniz olmalıdır.');
        }

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

            $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
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

        $redirectRoute = auth()->user()->isSubscriber() ? 'subscriber.dashboard' : 'apartments.index';
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

    /**
     * Destroy all data for the current apartment (except accounts).
     */
    public function destroyAll(Request $request, string $id)
    {
        $apartment = Apartment::query()
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->findOrFail($id);

        $isOwner = $this->isOwnerOf($apartment);

        abort_unless($isOwner || auth()->user()->isAdmin(), 403);

        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'in:tüm verilerin silinmesini kabul ediyorum'],
        ], [
            'confirmation.in' => 'Onay metni hatalı. Lütfen "tüm verilerin silinmesini kabul ediyorum" yazın.',
        ]);

        DB::transaction(function () use ($apartment) {
            // Delete payment allocations
            PaymentAllocation::whereHas('payment', function ($query) use ($apartment) {
                $query->where('apartment_id', $apartment->id);
            })->delete();

            // Delete cash transactions
            CashTransaction::where('apartment_id', $apartment->id)->delete();

            // Delete cash boxes
            CashBox::where('apartment_id', $apartment->id)->delete();

            // Delete payments
            Payment::where('apartment_id', $apartment->id)->delete();

            // Delete dues
            Due::where('apartment_id', $apartment->id)->delete();

            // Delete due batches
            DueBatch::where('apartment_id', $apartment->id)->delete();

            // Delete due plans
            \App\Models\DuePlan::where('apartment_id', $apartment->id)->delete();

            // Delete expenses
            Expense::where('apartment_id', $apartment->id)->delete();

            // Delete account transactions
            AccountTransaction::where('apartment_id', $apartment->id)->delete();

            // Delete tenant assignments
            TenantAssignment::whereHas('unit', function ($query) use ($apartment) {
                $query->where('apartment_id', $apartment->id);
            })->delete();

            // Delete unit owner histories
            UnitOwnerHistory::whereHas('unit', function ($query) use ($apartment) {
                $query->where('apartment_id', $apartment->id);
            })->delete();

            // Reset units (keep account references but clear other details)
            Unit::where('apartment_id', $apartment->id)->update([
                'floor' => null,
                'block' => null,
                'resident_name' => null,
                'phone' => null,
                'square_meters' => null,
                'share_coefficient' => null,
            ]);

            // Delete categories (except default ones)
            $defaultCategoryNames = ['Aidat', 'Demirbaş', 'Elektrik', 'Su', 'Asansör', 'Temizlik', 'Yönetim', 'Bakım', 'Diğer'];
            Category::where('apartment_id', $apartment->id)
                ->whereNotIn('name', $defaultCategoryNames)
                ->delete();

            // Note: Accounts are NOT deleted as per requirement
            // Note: Apartment is NOT deleted as per requirement
        });

        return redirect()->route('dashboard')->with('status', 'Tüm veriler silindi (hesaplar hariç).');
    }

    /**
     * Deactivate current apartment and redirect to create new apartment.
     */
    public function resetAndRenew(Request $request, string $id)
    {
        $apartment = Apartment::query()
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->findOrFail($id);

        $isOwner = $this->isOwnerOf($apartment);

        abort_unless($isOwner || auth()->user()->isAdmin(), 403);

        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'in:apartmanın silinmesini kabul ediyorum'],
        ], [
            'confirmation.in' => 'Onay metni hatalı. Lütfen "apartmanın silinmesini kabul ediyorum" yazın.',
        ]);

        DB::transaction(function () use ($apartment) {
            // Deactivate apartment
            $apartment->update(['is_active' => false]);

            // Clear current apartment session
            session()->forget('current_apartment_id');
        });

        $redirectRoute = auth()->user()->isSubscriber() ? 'subscriber.apartments.create' : 'apartments.create';
        return redirect()->route($redirectRoute)->with('status', 'Apartman pasife alındı. Yeni apartman oluşturabilirsiniz.');
    }
}
