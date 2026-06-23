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

        $packages = Package::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.managers.show', compact('manager', 'apartments', 'packages', 'quota'));
    }

    public function updateSubscription(Request $request, User $manager)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $package = Package::findOrFail($validated['package_id']);

        $price = $validated['price'] ?? ($validated['period'] === 'yearly' ? $package->yearly_price : $package->monthly_price);

        UserSubscription::where('user_id', $manager->id)->update(['is_active' => false]);

        UserSubscription::create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'period' => $validated['period'],
            'price' => $price,
            'started_at' => now(),
            'expires_at' => $validated['period'] === 'yearly' ? now()->addYear() : now()->addMonth(),
            'is_active' => true,
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
