<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\SubscriptionPayment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Models\UserSubscription;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminManagerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $managers = User::query()
            ->where('role', 'manager')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->with('subscription.package')
            ->latest()
            ->paginate(20);

        $quota = app(UserApartmentQuota::class);

        return view('admin.managers.index', compact('managers', 'search', 'quota'));
    }

    public function show(User $manager, UserApartmentQuota $quota)
    {
        $manager->load([
            'subscription.package',
            'subscription.payments',
            'subscriptions.package',
            'subscriptions.payments',
            'quotaOverride',
        ]);

        $apartments = $manager->apartments()->withPivot('role', 'is_active')->latest()->get();

        $packages = Package::where('is_active', true)->with('features')->orderBy('sort_order')->get();

        // Prepare package features for JavaScript
        $packageFeatures = [];
        foreach ($packages as $pkg) {
            $autoDuesFeature = $pkg->features->where('feature_key', 'Otomatik aidat planlama')->first();
            $userPortalFeature = $pkg->features->where('feature_key', 'Kullanıcı portalı erişimi')->first();
            $reportsFeature = $pkg->features->where('feature_key', 'Hesap ekstresi ve raporlar')->first();
            $multiApartmentFeature = $pkg->features->where('feature_key', 'Çoklu apartman yönetimi')->first();

            $packageFeatures[$pkg->id] = [
                'feature_auto_dues' => $autoDuesFeature ? $autoDuesFeature->is_enabled : false,
                'feature_user_portal' => $userPortalFeature ? $userPortalFeature->is_enabled : false,
                'feature_reports' => $reportsFeature ? $reportsFeature->is_enabled : false,
                'feature_multi_apartment' => $multiApartmentFeature ? $multiApartmentFeature->is_enabled : false,
                'multi_apartment_limit' => $pkg->multi_apartment_limit,
                'apartment_limit' => $pkg->apartment_limit,
                'monthly_price' => $pkg->monthly_price,
                'yearly_price' => $pkg->yearly_price,
            ];
        }

        $pendingSubscription = $manager->subscriptions()->pending()->with('package')->first();

        return view('admin.managers.show', compact('manager', 'apartments', 'packages', 'quota', 'packageFeatures', 'pendingSubscription'));
    }

    public function updateCurrentSubscription(Request $request, User $manager)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'multi_apartment_limit_override' => ['nullable', 'integer', 'min:0'],
            'max_apartments' => ['nullable', 'integer', 'min:0'],
        ]);

        $subscription = $manager->subscription;

        if (! $subscription) {
            return back()->withErrors(['subscription' => 'Aktif abonelik bulunamadı.']);
        }

        $subscription->update([
            'notes' => $validated['notes'] ?? $subscription->notes,
            'feature_auto_dues' => $request->input('feature_auto_dues') == '1',
            'feature_user_portal' => $request->input('feature_user_portal') == '1',
            'feature_reports' => $request->input('feature_reports') == '1',
            'feature_multi_apartment' => $request->input('feature_multi_apartment') == '1',
            'multi_apartment_limit_override' => $validated['multi_apartment_limit_override'] ?? null,
        ]);

        if ($request->filled('max_apartments')) {
            UserQuotaOverride::updateOrCreate(
                ['user_id' => $manager->id],
                ['max_apartments' => $validated['max_apartments']]
            );
        } else {
            $manager->quotaOverride?->delete();
        }

        return back()->with('status', 'Mevcut abonelik güncellendi.');
    }

    public function storeSubscriptionOrder(Request $request, User $manager)
    {
        $validated = $request->validate([
            'order.package_id' => ['required', 'exists:packages,id'],
            'order.period' => ['required', Rule::in(['monthly', 'yearly'])],
            'order.price' => ['required', 'numeric', 'min:0'],
            'is_paid' => ['required', 'boolean'],
            'payment_date' => ['nullable', 'date', 'required_if:is_paid,1'],
            'payment_method' => ['nullable', 'string', 'max:50', 'required_if:is_paid,1'],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'order.feature_auto_dues' => ['nullable', 'boolean'],
            'order.feature_user_portal' => ['nullable', 'boolean'],
            'order.feature_reports' => ['nullable', 'boolean'],
            'order.feature_multi_apartment' => ['nullable', 'boolean'],
            'order.multi_apartment_limit_override' => ['nullable', 'integer', 'min:0'],
        ]);

        $order = $validated['order'];

        $package = Package::with('features')->findOrFail($order['package_id']);
        $isTrial = $package->is_trial;
        $isPaid = (bool) $validated['is_paid'];

        $defaultFeatureAutoDues = $package->features->where('feature_key', 'Otomatik aidat planlama')->first()?->is_enabled ?? false;
        $defaultFeatureUserPortal = $package->features->where('feature_key', 'Kullanıcı portalı erişimi')->first()?->is_enabled ?? false;
        $defaultFeatureReports = $package->features->where('feature_key', 'Hesap ekstresi ve raporlar')->first()?->is_enabled ?? false;
        $defaultFeatureMultiApartment = $package->features->where('feature_key', 'Çoklu apartman yönetimi')->first()?->is_enabled ?? false;

        $finalFeatureAutoDues = $request->filled('order.feature_auto_dues') ? ($request->input('order.feature_auto_dues') == '1') : $defaultFeatureAutoDues;
        $finalFeatureUserPortal = $request->filled('order.feature_user_portal') ? ($request->input('order.feature_user_portal') == '1') : $defaultFeatureUserPortal;
        $finalFeatureReports = $request->filled('order.feature_reports') ? ($request->input('order.feature_reports') == '1') : $defaultFeatureReports;
        $finalFeatureMultiApartment = $request->filled('order.feature_multi_apartment') ? ($request->input('order.feature_multi_apartment') == '1') : $defaultFeatureMultiApartment;
        $finalMultiApartmentLimit = $request->filled('order.multi_apartment_limit_override')
            ? $order['multi_apartment_limit_override']
            : ($finalFeatureMultiApartment ? $package->multi_apartment_limit : null);

        $status = $isTrial || $isPaid ? UserSubscription::STATUS_ACTIVE : UserSubscription::STATUS_PENDING;
        $isActive = $status === UserSubscription::STATUS_ACTIVE;

        $expiresAt = null;
        if ($isActive) {
            $expiresAt = $order['period'] === 'yearly' ? now()->addYear() : now()->addMonth();
            if ($isTrial) {
                $expiresAt = now()->addMonths(SystemSetting::getTrialDuration());
            }
        }

        // If this is an active (paid or trial) order, close the current active subscription.
        if ($isActive) {
            $this->closeActiveSubscription($manager);
        }

        $subscription = UserSubscription::create([
            'user_id' => $manager->id,
            'package_id' => $order['package_id'],
            'period' => $order['period'],
            'price' => $order['price'],
            'started_at' => now(),
            'expires_at' => $expiresAt,
            'is_active' => $isActive,
            'is_trial' => $isTrial,
            'status' => $status,
            'notes' => $validated['notes'] ?? null,
            'feature_auto_dues' => $finalFeatureAutoDues,
            'feature_user_portal' => $finalFeatureUserPortal,
            'feature_reports' => $finalFeatureReports,
            'feature_multi_apartment' => $finalFeatureMultiApartment,
            'multi_apartment_limit_override' => $finalMultiApartmentLimit,
        ]);

        if ($isPaid && ! $isTrial) {
            $subscription->payments()->create([
                'amount' => $order['price'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? 'havale',
                'reference_code' => $validated['reference_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        $message = $isActive ? 'Yeni abonelik siparişi oluşturuldu ve aktif edildi.' : 'Yeni abonelik siparişi oluşturuldu; ödeme onayı bekleniyor.';

        return back()->with('status', $message);
    }

    public function approveSubscriptionOrder(Request $request, User $manager, UserSubscription $subscription)
    {
        if ($subscription->user_id !== $manager->id) {
            return back()->withErrors(['subscription' => 'Abonelik bu kullanıcıya ait değil.']);
        }

        if (! $subscription->isPending()) {
            return back()->withErrors(['subscription' => 'Bu abonelik zaten onaylı veya iptal edilmiş.']);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50', Rule::in(['havale', 'nakit'])],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentMethod = $validated['payment_method'];
        $referenceCode = $validated['reference_code'] ?? null;

        if ($paymentMethod === 'nakit' && empty($referenceCode)) {
            $referenceCode = 'NKT-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4));
        }

        $this->closeActiveSubscription($manager);

        $expiresAt = $subscription->period === 'yearly' ? now()->addYear() : now()->addMonth();

        $subscription->update([
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $subscription->payments()->create([
            'amount' => $subscription->price,
            'payment_date' => now(),
            'payment_method' => $paymentMethod,
            'reference_code' => $referenceCode,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', 'Ödeme onaylandı ve abonelik aktif edildi.');
    }

    public function rejectSubscriptionOrder(Request $request, User $manager, UserSubscription $subscription)
    {
        if ($subscription->user_id !== $manager->id) {
            return back()->withErrors(['subscription' => 'Abonelik bu kullanıcıya ait değil.']);
        }

        if (! $subscription->isPending()) {
            return back()->withErrors(['subscription' => 'Bu abonelik zaten onaylı veya iptal edilmiş.']);
        }

        $validated = $request->validate([
            'rejection_notes' => ['nullable', 'string'],
        ]);

        $notes = $subscription->notes;
        if (! empty($validated['rejection_notes'])) {
            $notes = $validated['rejection_notes'];
        }

        $subscription->update([
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
            'ended_at' => now(),
            'notes' => $notes,
        ]);

        return back()->with('status', 'Sipariş reddedildi.');
    }

    public function updateQuota(Request $request, User $manager)
    {
        $validated = $request->validate([
            'max_apartments' => ['required', 'integer', 'min:0'],
        ]);

        UserQuotaOverride::updateOrCreate(
            ['user_id' => $manager->id],
            ['max_apartments' => $validated['max_apartments']]
        );

        return back()->with('status', 'Apartman kotası güncellendi.');
    }

    public function extendTrial(Request $request, User $manager)
    {
        $validated = $request->validate([
            'days'       => ['nullable', 'integer', 'min:1', 'max:365'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $subscription = $manager->subscription;

        if (! $subscription || ! $subscription->is_trial) {
            return back()->withErrors(['trial' => 'Bu kullanıcının aktif bir deneme aboneliği yok.']);
        }

        if (isset($validated['expires_at'])) {
            $newExpiry = \Carbon\Carbon::parse($validated['expires_at'])->endOfDay();
        } elseif (isset($validated['days'])) {
            $base = $subscription->expires_at && $subscription->expires_at->isFuture()
                ? $subscription->expires_at
                : now();
            $newExpiry = $base->addDays($validated['days']);
        } else {
            return back()->withErrors(['trial' => 'Gün sayısı veya bitiş tarihi giriniz.']);
        }

        $subscription->update([
            'expires_at' => $newExpiry,
            'is_active'  => true,
        ]);

        return back()->with('status', 'Deneme süresi ' . $newExpiry->format('d.m.Y') . ' tarihine uzatıldı.');
    }

    public function reactivateSubscription(Request $request, User $manager, UserSubscription $subscription)
    {
        if ($subscription->user_id !== $manager->id) {
            return back()->withErrors(['subscription' => 'Abonelik bu kullanıcıya ait değil.']);
        }

        if ($subscription->is_active) {
            return back()->withErrors(['subscription' => 'Abonelik zaten aktif.']);
        }

        if ($subscription->isCancelled()) {
            return back()->withErrors(['subscription' => 'İptal edilen abonelik geri yüklenemez.']);
        }

        $this->closeActiveSubscription($manager);

        $subscription->update([
            'is_active' => true,
            'status' => UserSubscription::STATUS_ACTIVE,
            'ended_at' => null,
        ]);

        return back()->with('status', 'Abonelik geri yüklendi.');
    }

    public function cancelSubscription(Request $request, User $manager)
    {
        $validated = $request->validate([
            'cancellation_notes' => ['nullable', 'string'],
        ]);

        $subscription = $manager->subscription;

        if (! $subscription) {
            return back()->withErrors(['subscription' => 'Aktif abonelik bulunamadı.']);
        }

        $subscription->update([
            'is_active' => false,
            'status' => UserSubscription::STATUS_CANCELLED,
            'ended_at' => now(),
            'notes' => $validated['cancellation_notes'] ?? null,
        ]);

        return back()->with('status', 'Abonelik iptal edildi.');
    }

    private function closeActiveSubscription(User $manager): void
    {
        $manager->subscription?->update([
            'is_active' => false,
            'status' => UserSubscription::STATUS_CANCELLED,
            'ended_at' => now(),
        ]);
    }
}
