<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Models\UserSubscription;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;
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
        $manager->load(['subscription.package', 'quotaOverride']);

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

        return view('admin.managers.show', compact('manager', 'apartments', 'packages', 'quota', 'packageFeatures'));
    }

    public function updateSubscription(Request $request, User $manager)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['nullable', 'numeric', 'min:0'],
            'multi_apartment_limit_override' => ['nullable', 'integer', 'min:0'],
            'max_apartments' => ['nullable', 'integer', 'min:0'],
        ]);

        $package = Package::with('features')->findOrFail($validated['package_id']);

        $price = $validated['price'] ?? ($validated['period'] === 'yearly' ? $package->yearly_price : $package->monthly_price);

        // Get package features as defaults
        $featureAutoDues = $package->features->where('feature_key', 'Otomatik aidat planlama')->first()?->is_enabled ?? false;
        $featureUserPortal = $package->features->where('feature_key', 'Kullanıcı portalı erişimi')->first()?->is_enabled ?? false;
        $featureReports = $package->features->where('feature_key', 'Hesap ekstresi ve raporlar')->first()?->is_enabled ?? false;
        $featureMultiApartment = $package->features->where('feature_key', 'Çoklu apartman yönetimi')->first()?->is_enabled ?? false;

        // Use override values from form (checkbox values), otherwise use package defaults
        // Hidden inputs are always present, so we check if checkbox was checked (value='1')
        $finalFeatureAutoDues = $request->input('feature_auto_dues') == '1';
        $finalFeatureUserPortal = $request->input('feature_user_portal') == '1';
        $finalFeatureReports = $request->input('feature_reports') == '1';
        $finalFeatureMultiApartment = $request->input('feature_multi_apartment') == '1';
        $finalMultiApartmentLimit = $request->filled('multi_apartment_limit_override') ? $validated['multi_apartment_limit_override'] : ($finalFeatureMultiApartment ? $package->multi_apartment_limit : null);

        // Handle quota override
        if ($request->filled('max_apartments')) {
            UserQuotaOverride::updateOrCreate(
                ['user_id' => $manager->id],
                ['max_apartments' => $validated['max_apartments']]
            );
        } else {
            // If empty, remove the override to use package default
            $manager->quotaOverride?->delete();
        }

        UserSubscription::where('user_id', $manager->id)->update(['is_active' => false]);

        UserSubscription::create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'period' => $validated['period'],
            'price' => $price,
            'started_at' => now(),
            'expires_at' => $validated['period'] === 'yearly' ? now()->addYear() : now()->addMonth(),
            'is_active' => true,
            'feature_auto_dues' => $finalFeatureAutoDues,
            'feature_user_portal' => $finalFeatureUserPortal,
            'feature_reports' => $finalFeatureReports,
            'feature_multi_apartment' => $finalFeatureMultiApartment,
            'multi_apartment_limit_override' => $finalMultiApartmentLimit,
        ]);

        return back()->with('status', 'Abonelik güncellendi.');
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
}
