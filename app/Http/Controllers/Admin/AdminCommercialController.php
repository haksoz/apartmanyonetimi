<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\PriceBand;
use App\Models\PriceCampaign;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCommercialController extends Controller
{
    public function index()
    {
        $bands = PriceBand::query()->orderBy('sort_order')->get();
        $campaigns = PriceCampaign::query()->with('band')->latest()->get();
        $features = Feature::query()->orderBy('name')->get();
        $settings = SystemSetting::query()->firstOrCreate([], [
            'trial_duration_months' => 2,
            'subscription_tax_rate' => 20,
        ]);

        return view('admin.commercial.index', compact('bands', 'campaigns', 'features', 'settings'));
    }

    public function updateTax(Request $request)
    {
        $validated = $request->validate([
            'subscription_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        SystemSetting::query()->firstOrCreate([], ['trial_duration_months' => 2])->update($validated);

        return $this->saved($request, 'commercial-tax');
    }

    public function updateBand(Request $request, PriceBand $band)
    {
        $validated = $request->validate([
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $band->update([
            'monthly_price' => $band->is_quote ? null : $validated['monthly_price'],
            'yearly_price' => $band->is_quote ? null : $validated['yearly_price'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->saved($request, 'price-band-'.$band->id);
    }

    public function storeCampaign(Request $request)
    {
        $request->merge([
            'price_band_id' => $request->input('price_band_id') ?: null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_band_id' => ['nullable', 'integer', 'exists:price_bands,id'],
            'percent_off' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_off' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $campaign = PriceCampaign::create([
            ...$validated,
            'is_active' => true,
        ]);

        return $this->saved($request, 'commercial-campaign', [
            'campaign' => $campaign->name.' · '.($campaign->band?->label ?? 'Tüm bantlar').' · Aktif',
        ]);
    }

    public function updateFeature(Request $request, Feature $feature)
    {
        $validated = $request->validate([
            'access' => ['required', Rule::in([Feature::ACCESS_FREE, Feature::ACCESS_PAID])],
        ]);

        $feature->update($validated);

        return $this->saved($request, 'commercial-feature-'.$feature->id);
    }
    private function saved(Request $request, string $form, array $data = [])
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Kaydedildi', ...$data]);
        }

        return redirect()->to(route('admin.commercial.index').'#'.$form)
            ->with('commercial_saved', $form);
    }

}
