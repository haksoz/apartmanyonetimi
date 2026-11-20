<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\BillingProfile;
use Illuminate\Http\Request;

class SubscriberBillingProfileController extends Controller
{
    public function index()
    {
        $profiles = auth()->user()
            ->billingProfiles()
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return view('subscriber.billing-profiles.index', compact('profiles'));
    }

    public function create()
    {
        return view('subscriber.billing-profiles.form', [
            'profile' => new BillingProfile(['party_type' => BillingProfile::TYPE_INDIVIDUAL, 'is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $attributes = $this->attributes($request);
        $profile = \Illuminate\Support\Facades\DB::transaction(function () use ($attributes, $request) {
            $user = \App\Models\User::query()->lockForUpdate()->findOrFail(auth()->id());
            if ($request->expectsJson()) {
                $query = $user->billingProfiles()->where('is_active', true);
                foreach ($attributes as $key => $value) {
                    $query->where($key, $value);
                }
                if ($existing = $query->first()) {
                    return $existing;
                }
            }

            return $user->billingProfiles()->create($attributes + ['is_active' => true]);
        });

        if ($request->expectsJson()) {
            app(\App\Support\CheckoutJourneyTracking::class)->billingSaved($request->user(), $request->input('checkout_journey'), $profile);
            return response()->json(['profile' => $profile->only(array_merge(['id'], array_keys(BillingProfile::rules())))]);
        }

        return redirect()
            ->route('subscriber.billing-profiles.index')
            ->with('status', 'Fatura profili oluşturuldu.');
    }

    public function edit(BillingProfile $billingProfile)
    {
        $this->owned($billingProfile);

        return view('subscriber.billing-profiles.form', [
            'profile' => $billingProfile,
        ]);
    }

    public function update(Request $request, BillingProfile $billingProfile)
    {
        $this->owned($billingProfile);

        if ($request->input('party_type') !== $billingProfile->party_type) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'party_type' => 'Kayıtlı profilin fatura türü değiştirilemez. Farklı bir fatura türü için yeni profil oluşturun.',
            ]);
        }
        $billingProfile->update($this->attributes($request));

        return redirect()
            ->route('subscriber.billing-profiles.index')
            ->with('status', 'Fatura profili güncellendi.');
    }

    public function active(BillingProfile $billingProfile)
    {
        $this->owned($billingProfile);
        $billingProfile->update([
            'is_active' => ! $billingProfile->is_active,
        ]);

        return redirect()
            ->route('subscriber.billing-profiles.index')
            ->with('status', $billingProfile->is_active ? 'Fatura profili yeniden kullanıma açıldı.' : 'Fatura profili pasife alındı.');
    }

    private function owned(BillingProfile $profile): void
    {
        abort_unless($profile->user_id === auth()->id(), 404);
    }

    /**
     * @return array<string, mixed>
     */
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
