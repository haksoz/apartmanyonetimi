<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminCustomerBillingProfileController extends Controller
{
    public function index(User $customer)
    {
        $this->ensureCustomer($customer);

        return view('admin.customers.billing-profiles.index', [
            'customer' => $customer,
            'profiles' => $customer->billingProfiles()->orderByDesc('is_active')->orderBy('label')->orderBy('id')->get(),
        ]);
    }

    public function create(User $customer)
    {
        $this->ensureCustomer($customer);

        return view('admin.customers.billing-profiles.form', [
            'customer' => $customer,
            'profile' => new BillingProfile(['party_type' => BillingProfile::TYPE_INDIVIDUAL, 'is_active' => true]),
        ]);
    }

    public function store(Request $request, User $customer)
    {
        $this->ensureCustomer($customer);
        $customer->billingProfiles()->create($this->attributes($request) + ['is_active' => true]);

        return redirect()->route('admin.customers.billing-profiles.index', $customer)->with('status', 'Fatura profili oluşturuldu.');
    }

    public function edit(User $customer, BillingProfile $billingProfile)
    {
        $this->ensureCustomer($customer, $billingProfile);

        return view('admin.customers.billing-profiles.form', ['customer' => $customer, 'profile' => $billingProfile]);
    }

    public function update(Request $request, User $customer, BillingProfile $billingProfile)
    {
        $this->ensureCustomer($customer, $billingProfile);
        if ($request->input('party_type') !== $billingProfile->party_type) {
            throw ValidationException::withMessages(['party_type' => 'Kayıtlı profilin fatura türü değiştirilemez. Farklı bir fatura türü için yeni profil oluşturun.']);
        }
        $billingProfile->update($this->attributes($request));

        return redirect()->route('admin.customers.billing-profiles.index', $customer)->with('status', 'Fatura profili güncellendi.');
    }

    public function active(User $customer, BillingProfile $billingProfile)
    {
        $this->ensureCustomer($customer, $billingProfile);
        $billingProfile->update(['is_active' => ! $billingProfile->is_active]);

        return redirect()->route('admin.customers.billing-profiles.index', $customer)->with('status', $billingProfile->is_active ? 'Fatura profili yeniden kullanıma açıldı.' : 'Fatura profili pasife alındı.');
    }

    private function ensureCustomer(User $customer, ?BillingProfile $profile = null): void
    {
        abort_if($customer->isAdminPanelUser(), 404);
        abort_if($profile && $profile->user_id !== $customer->id, 404);
    }

    private function attributes(Request $request): array
    {
        $validated = $request->validate(BillingProfile::rules());
        $attributes = [];
        foreach (array_keys(BillingProfile::rules()) as $key) {
            $value = $validated[$key] ?? null;
            $attributes[$key] = $value === '' ? null : $value;
        }

        return $attributes;
    }
}
