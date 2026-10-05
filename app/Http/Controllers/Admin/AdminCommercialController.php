<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\PriceBand;
use App\Models\PriceCampaign;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCommercialController extends Controller
{
    public function index()
    {
        $bands = PriceBand::query()->orderBy('sort_order')->get();
        $campaigns = PriceCampaign::query()->with('band')->latest()->get();
        $features = Feature::query()->orderBy('name')->get();

        return view('admin.commercial.index', compact('bands', 'campaigns', 'features'));
    }

    public function updateBand(Request $request, PriceBand $band)
    {
        $validated = $request->validate([
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $band->update([
            'monthly_price' => $band->is_quote ? null : $validated['monthly_price'],
            'yearly_price' => $band->is_quote ? null : $validated['yearly_price'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', $band->label.' bandı güncellendi.');
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
            'amount_off' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        PriceCampaign::create([
            ...$validated,
            'is_active' => true,
        ]);

        return back()->with('status', 'Kampanya eklendi.');
    }

    public function updateFeature(Request $request, Feature $feature)
    {
        $validated = $request->validate([
            'access' => ['required', Rule::in([Feature::ACCESS_FREE, Feature::ACCESS_PAID])],
        ]);

        $feature->update($validated);

        return back()->with('status', $feature->name.' erişimi güncellendi.');
    }
}
