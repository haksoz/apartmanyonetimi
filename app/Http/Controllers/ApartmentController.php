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
use App\Services\LateCompensationEnrollment;
use App\Support\ApartmentCommercial;
use App\Support\BulkImportGuard;
use App\Support\ApartmentReset\ApartmentReset;
use App\Support\ApartmentReset\ResetPolicy;
use App\Support\CurrentApartment;
use App\Support\LateCompensationScope;
use App\Support\LateCompensationSettings;
use App\Support\LegalConsent;
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
            return redirect()->route('subscriber.dashboard');
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

        $lateCompensationPolicies = $this->canManageLateCompensation($currentApartment)
            ? $currentApartment->lateCompensationPolicies()->latest('effective_from')->get()
            : null;

        $pending = session('late_compensation_enrollment');
        $lateCompensationPreview = $lateCompensationPolicies !== null && ($pending['apartment'] ?? null) === $currentApartment->id
            && ($pending['user'] ?? null) === auth()->id() ? $pending['preview'] : null;

        return view('apartments.show', [
            'apartment' => $currentApartment,
            'isOwner' => $isOwner,
            'hasImported' => $hasImported,
            'lateCompensationPolicies' => $lateCompensationPolicies,
            'lateCompensationPreview' => $lateCompensationPreview,
            'serviceHistory' => $serviceHistory,
            'importBlockReason' => $isOwner ? app(BulkImportGuard::class)->reason($currentApartment) : null,
            'importDeleteReason' => $isOwner ? app(BulkImportGuard::class)->deleteReason($currentApartment) : null,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(UserApartmentQuota $quota)
    {
        return view('apartments.create', [
            'quotaBlocked' => ! $quota->canCreate(auth()->user()),
        ]);
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

        if (! app(UserApartmentQuota::class)->canCreate($request->user())) {
            return back()->withErrors(['name' => UserApartmentQuota::LIMIT_MESSAGE])->withInput();
        }

        $user = auth()->user();
        $decision = app(ApartmentCommercial::class)->assess((int) $validated['unit_count']);

        if ($decision['quote']) {
            QuoteRequest::record($user, $validated['name'], (int) $validated['unit_count']);

            return redirect()->route('subscriber.dashboard')->with('status', ApartmentCommercial::QUOTE_MESSAGE);
        }

        app(LegalConsent::class)->requireForApartment($request, false);

        $apartment = DB::transaction(function () use ($validated, $user, $decision) {
            app(\App\Support\UserApartmentQuota::class)->assertCanCreate($user);

            $apartment = Apartment::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'province' => $validated['province'] ?? null,
                'district' => $validated['district'] ?? null,
                'unit_count' => $validated['unit_count'],
                'account_opening_date' => $validated['account_opening_date'],
                'billing_plan' => $decision['plan'],
            ]);

            $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
            app(\App\Support\ApartmentManagementHistory::class)->startInitial($apartment, $user);
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
        $request->session()->forget('open_apartment_requested');

        return redirect()->route('apartments.wizard.manager', $apartment)
            ->with('status', 'Apartman oluşturuldu. Şimdi yönetici bilgilerinizi tamamlayın.');
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

        $importBlockReason = $isOwner ? app(BulkImportGuard::class)->reason($apartment) : null;
        $importDeleteReason = $isOwner ? app(BulkImportGuard::class)->deleteReason($apartment) : null;

        return view('apartments.show', compact('apartment', 'isOwner', 'hasImported', 'serviceHistory', 'importBlockReason', 'importDeleteReason'));
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

        $details = [
            'name' => $validated['name'],
            'address' => $validated['address'],
            'province' => $validated['province'] ?: null,
            'district' => $validated['district'] ?: null,
        ];

        $apartment->update($details);

        $redirectRoute = auth()->user()->isSubscriber() ? 'subscriber.apartments.show' : 'apartments.show';
        return redirect()->route($redirectRoute, $apartment)->with('status', 'Apartman bilgileri güncellendi.');
    }

    public function updateLateCompensation(Request $request, Apartment $apartment)
    {
        abort_unless((int) $request->session()->get(CurrentApartment::SESSION_KEY) === $apartment->id, 404);
        abort_unless($this->canManageLateCompensation($apartment), 403);

        $policyInput = $request->validate([
            'late_compensation' => ['required', 'array:is_active,monthly_rate,starts_on,effective_from,include_open'],
            'late_compensation.is_active' => ['required', 'boolean'],
            'late_compensation.monthly_rate' => ['nullable', 'numeric', 'decimal:0,6', 'min:0', 'max:9999.999999'],
            'late_compensation.starts_on' => ['nullable', 'date_format:Y-m-d'],
            'late_compensation.effective_from' => ['nullable', 'date_format:Y-m-d'],
            'late_compensation.include_open' => ['sometimes', 'boolean'],
        ])['late_compensation'];

        $include = ! empty($policyInput['include_open']);
        $enrollment = app(LateCompensationEnrollment::class);
        if ($request->boolean('preview_late_compensation')) {
            if (! $include) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'late_compensation.include_open' => 'Önizleme için mevcut açık borçları kapsama alma seçeneğini işaretleyin.',
                ]);
            }
            $preview = $enrollment->preview($apartment, $policyInput);
            $request->session()->put('late_compensation_enrollment', [
                'apartment' => $apartment->id, 'user' => auth()->id(), 'preview' => $preview,
            ]);

            return redirect()->route('apartments.index')->withInput();
        }

        DB::transaction(function () use ($apartment, $policyInput, $include, $enrollment, $request) {
            $locked = Apartment::query()->lockForUpdate()->findOrFail($apartment->id);
            $preview = null;
            if ($include) {
                $pending = $request->session()->get('late_compensation_enrollment');
                $locked->accounts()->withTrashed()->orderBy('id')->lockForUpdate()->get(['id']);
                $preview = $enrollment->preview($locked, $policyInput);
                if (($pending['apartment'] ?? null) !== $locked->id || ($pending['user'] ?? null) !== auth()->id()
                    || ($pending['preview'] ?? null) !== $preview) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'late_compensation.include_open' => 'Kapsama alınacak açık borçları yeniden önizleyin.',
                    ]);
                }
            }
            app(LateCompensationSettings::class)->save($locked, $policyInput, auth()->id());
            if ($preview !== null) {
                $enrollment->enroll($locked, $preview);
            }
        });
        $request->session()->forget('late_compensation_enrollment');

        return redirect()->route('apartments.index')->with('status', 'Gecikme Tazminatı ayarları güncellendi.');
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

    public function renewForm(string $id)
    {
        $apartment = $this->managedApartment($id);
        abort_unless($this->isOwnerOf($apartment), 403);

        return view('apartments.renew-setup', compact('apartment'));
    }

    /**
     * Daire sayısı kuralına göre aynı apartmanda kurulumu yeniler veya ayrı ücretsiz apartman açar.
     */
    public function renewSetup(Request $request, string $id, ApartmentReset $reset, LegalConsent $consent)
    {
        $apartment = $this->managedApartment($id);
        abort_unless($this->isOwnerOf($apartment), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'account_opening_date' => ['required', 'date'],
            'current_password' => ['required', 'current_password'],
            'unit_count' => ['required', 'integer', 'min:1', 'max:500'],
            'confirmation' => ['required', 'string', 'in:kurulumun yenilenmesini kabul ediyorum'],
            'accept_new_free_apartment' => ['nullable', 'boolean'],
        ], [
            'confirmation.in' => 'Onay metni hatalı. Lütfen "kurulumun yenilenmesini kabul ediyorum" yazın.',
        ]);

        $unitCount = (int) $validated['unit_count'];
        $profile = [
            'name' => $validated['name'],
            'address' => $validated['address'],
            'province' => $validated['province'] ?? null,
            'district' => $validated['district'] ?? null,
            'account_opening_date' => $validated['account_opening_date'],
        ];
        $decision = $reset->policy()->decide($apartment, $unitCount);

        if ($decision['decision'] === ResetPolicy::QUOTE) {
            QuoteRequest::record($request->user(), $validated['name'], $unitCount);
            $reset->renew($request->user(), $apartment, $unitCount, $decision, $request);

            return back()->with('status', ApartmentCommercial::QUOTE_MESSAGE)->withInput();
        }

        $consent->requireForApartment($request, false);

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
                ...ApartmentDataOperation::context($request->user(), $apartment),
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

        $outcome = $reset->renew($request->user(), $apartment, $unitCount, $decision, $request, $request->boolean('skip_archive_check'), $profile);

        if ($outcome instanceof ApartmentDataOperation && $outcome->result === ApartmentDataOperation::RESULT_ARCHIVE_FAILED) {
            return back()->withErrors([
                'confirmation' => 'Silme başlatılmadı. Yedekleme altyapısı henüz hazır değil.',
            ])->withInput();
        }

        if ($outcome instanceof Apartment) {
            app(CurrentApartment::class)->setFor($request->user(), $outcome->id);

            return redirect()->route('apartments.wizard.cash-box', $outcome)
                ->with('status', 'Yeni ücretsiz apartman açıldı. Eski apartman pasife alındı. Ücretli kullanım hakkı eski apartmanda duruyor.');
        }

        $consent->recordResidentData($request->user(), $apartment, $request);

        return redirect()->route('apartments.wizard.cash-box', $apartment)
            ->with('status', 'Kurulum yenilendi. Abonelik bu apartmanda duruyor. Silinen verilerin yedeği alınmadı.');
    }

    private function canManageLateCompensation(Apartment $apartment): bool
    {
        return app(LateCompensationScope::class)->previewEnabled()
            && (auth()->user()->isAdmin() || $apartment->managers()->whereKey(auth()->id())->exists());
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
