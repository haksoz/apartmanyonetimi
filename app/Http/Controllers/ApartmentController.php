<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\ApartmentDataOperation;
use App\Models\Category;
use App\Models\QuoteRequest;
use App\Models\SubscriptionItem;
use App\Models\Unit;
use App\Support\ApartmentCommercial;
use App\Support\ApartmentReset\ApartmentReset;
use App\Support\ApartmentReset\ResetPolicy;
use App\Support\CurrentApartment;
use App\Support\LegalConsent;
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

        $serviceHistory = collect();
        if ($isOwner) {
            $serviceHistory = SubscriptionItem::query()
                ->where('apartment_id', $currentApartment->id)
                ->with('subscription.user')
                ->get()
                ->sortByDesc(fn (SubscriptionItem $item) => $item->subscription?->started_at?->timestamp ?? $item->id);
        }

        $hasImported = AccountTransaction::where('apartment_id', $currentApartment->id)
            ->where('is_imported', true)
            ->exists();

        return view('apartments.show', [
            'apartment' => $currentApartment,
            'isOwner' => $isOwner,
            'hasImported' => $hasImported,
            'serviceHistory' => $serviceHistory,
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
    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'unit_count' => ['required', 'integer', 'min:1', 'max:500'],
            'account_opening_date' => ['required', 'date'],
        ]);

        $user = auth()->user();
        $decision = app(ApartmentCommercial::class)->assess((int) $validated['unit_count']);

        if ($decision['quote']) {
            QuoteRequest::record($user, $validated['name'], (int) $validated['unit_count']);

            return back()->with('status', ApartmentCommercial::QUOTE_MESSAGE)->withInput();
        }

        app(LegalConsent::class)->requireForApartment($request, false);

        $apartment = DB::transaction(function () use ($validated, $user, $decision) {
            $apartment = Apartment::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'province' => $validated['province'] ?? null,
                'district' => $validated['district'] ?? null,
                'unit_count' => $validated['unit_count'],
                'billing_plan' => $decision['plan'],
            ]);

            $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
            app(\App\Support\SubscriptionCheckout::class)->openFree($user, $apartment);
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

            return $apartment;
        });

        $currentApartment->setFor($user, $apartment->id);
        app(LegalConsent::class)->recordResidentData($user, $apartment, $request);

        return redirect()->route('apartments.wizard.cash-box', $apartment)
            ->with('status', 'Apartman oluşturuldu. Şimdi kasanızı oluşturun.');
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

        $serviceHistory = collect();
        if ($isOwner) {
            $serviceHistory = SubscriptionItem::query()
                ->where('apartment_id', $apartment->id)
                ->with('subscription.user')
                ->get()
                ->sortByDesc(fn (SubscriptionItem $item) => $item->subscription?->started_at?->timestamp ?? $item->id);
        }

        $hasImported = AccountTransaction::where('apartment_id', $apartment->id)
            ->where('is_imported', true)
            ->exists();

        return view('apartments.show', compact('apartment', 'isOwner', 'hasImported', 'serviceHistory'));
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

        $apartment->load(['user', 'managerUnit']);

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
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
        ]);

        $apartment->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'province' => $validated['province'] ?: null,
            'district' => $validated['district'] ?: null,
        ]);

        $redirectRoute = auth()->user()->isSubscriber() ? 'subscriber.apartments.show' : 'apartments.show';
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
     * Aidat, gider, tahsilat ve kasa hareketini siler. Apartman ve abonelik durur.
     */
    public function destroyAll(Request $request, string $id, ApartmentReset $reset)
    {
        $apartment = $this->managedApartment($id);
        abort_unless($this->isOwnerOf($apartment), 403);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'confirmation' => ['required', 'string', 'in:tüm verilerin silinmesini kabul ediyorum'],
            'skip_archive_check' => ['accepted'],
        ], [
            'confirmation.in' => 'Onay metni hatalı. Lütfen "tüm verilerin silinmesini kabul ediyorum" yazın.',
            'skip_archive_check.accepted' => 'Silmek için yedekleme altyapısı kontrolünü atlamayı onaylayın.',
        ]);

        $operation = $reset->wipe($request->user(), $apartment, true);

        if ($operation->result === ApartmentDataOperation::RESULT_ARCHIVE_FAILED) {
            return back()->withErrors([
                'confirmation' => 'Silme başlatılmadı. Yedekleme altyapısı henüz hazır değil.',
            ]);
        }

        return redirect()->route('dashboard')->with('status', 'İşlem verileri silindi. Apartman, daire sayısı, hesaplar ve abonelik duruyor. Silinen verilerin yedeği alınmadı.');
    }

    /**
     * Daire sayısı kuralına göre aynı apartmanda kurulumu yeniler veya ayrı ücretsiz apartman açar.
     */
    public function renewSetup(Request $request, string $id, ApartmentReset $reset)
    {
        $apartment = $this->managedApartment($id);
        abort_unless($this->isOwnerOf($apartment), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'unit_count' => ['required', 'integer', 'min:1', 'max:500'],
            'confirmation' => ['required', 'string', 'in:kurulumun yenilenmesini kabul ediyorum'],
            'accept_new_free_apartment' => ['nullable', 'boolean'],
        ], [
            'confirmation.in' => 'Onay metni hatalı. Lütfen "kurulumun yenilenmesini kabul ediyorum" yazın.',
        ]);

        $unitCount = (int) $validated['unit_count'];
        $decision = $reset->policy()->decide($apartment, $unitCount);

        if ($decision['decision'] === ResetPolicy::QUOTE) {
            QuoteRequest::record($request->user(), $apartment->name, $unitCount);
            $reset->renew($request->user(), $apartment, $unitCount, $decision, $request);

            return back()->with('status', ApartmentCommercial::QUOTE_MESSAGE);
        }

        if ($decision['decision'] === ResetPolicy::BLOCKED_DECREASE) {
            $reset->renew($request->user(), $apartment, $unitCount, $decision, $request);

            $message = $decision['active']
                ? 'Ücretli dönem devam ederken daire sayısı, satın alınan sayıdan aşağı indirilemez.'
                : 'Daire sayısı, son ücretli bandın altına indirilemez.';

            return back()->withErrors([
                'unit_count' => $message,
            ])->withInput();
        }

        if ($decision['decision'] === ResetPolicy::NEW_FREE_APARTMENT && ! $request->boolean('accept_new_free_apartment')) {
            ApartmentDataOperation::query()->create([
                'user_id' => $request->user()->id,
                'apartment_id' => $apartment->id,
                'action' => ApartmentDataOperation::ACTION_NEW_APARTMENT,
                'result' => ApartmentDataOperation::RESULT_AWAITING,
                'archive_status' => ApartmentDataOperation::ARCHIVE_SKIPPED,
                'requested_unit_count' => $unitCount,
                'scope' => 'Yeni ücretsiz apartman onayı bekleniyor',
            ]);

            return back()->withErrors([
                'accept_new_free_apartment' => ResetPolicy::NEW_APARTMENT_WARNING,
            ])->withInput();
        }

        if ($decision['decision'] === ResetPolicy::SAME_APARTMENT && ! $request->boolean('skip_archive_check')) {
            return back()->withErrors([
                'skip_archive_check' => 'Silmek için yedekleme altyapısı kontrolünü atlamayı onaylayın.',
            ])->withInput();
        }

        $outcome = $reset->renew($request->user(), $apartment, $unitCount, $decision, $request, $request->boolean('skip_archive_check'));

        if ($outcome instanceof ApartmentDataOperation && $outcome->result === ApartmentDataOperation::RESULT_ARCHIVE_FAILED) {
            return back()->withErrors([
                'confirmation' => 'Silme başlatılmadı. Yedekleme altyapısı henüz hazır değil.',
            ])->withInput();
        }

        if ($outcome instanceof Apartment) {
            app(CurrentApartment::class)->setFor($request->user(), $outcome->id);

            return redirect()->route('apartments.wizard.cash-box', $outcome)
                ->with('status', 'Yeni ücretsiz apartman açıldı. Eski apartman ve ücretli kullanım hakkı yerinde duruyor.');
        }

        return redirect()->route('apartments.wizard.cash-box', $apartment)
            ->with('status', 'Kurulum yenilendi. Abonelik bu apartmanda duruyor. Silinen verilerin yedeği alınmadı.');
    }

    private function managedApartment(string $id): Apartment
    {
        return Apartment::query()
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->whereHas('members', function ($query) {
                    $query->whereKey(auth()->id());
                });
            })
            ->findOrFail($id);
    }
}
