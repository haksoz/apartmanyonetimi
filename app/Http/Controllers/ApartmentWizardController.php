<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\CashBox;
use App\Models\Category;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApartmentWizardController extends Controller
{
    public function cashBoxStep(Apartment $apartment): RedirectResponse|View
    {
        $this->authorizeApartment($apartment);

        if ($next = $this->redirectForCompletedSteps($apartment)) {
            return $next;
        }

        return view('apartments.wizard.cash-box', [
            'apartment' => $apartment,
            'activeStep' => 2,
        ]);
    }

    public function storeCashBox(Request $request, Apartment $apartment): RedirectResponse
    {
        $this->authorizeApartment($apartment);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
        ]);

        CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'iban' => $validated['iban'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('apartments.wizard.units', $apartment)->with('status', 'Kasa oluşturuldu. Şimdi daire bilgilerini girin.');
    }

    public function unitsStep(Apartment $apartment): RedirectResponse|View
    {
        $this->authorizeApartment($apartment);

        if (! $apartment->cashBoxes()->exists()) {
            return redirect()->route('apartments.wizard.cash-box', $apartment);
        }

        if ($apartment->setup_units_completed_at !== null) {
            return redirect()->route('apartments.wizard.categories', $apartment);
        }

        if ($apartment->setup_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        $units = $apartment->units()
            ->orderByRaw('CAST(unit_no AS UNSIGNED)')
            ->get();

        return view('apartments.wizard.units', [
            'apartment' => $apartment,
            'units' => $units,
            'activeStep' => 3,
        ]);
    }

    public function storeUnits(Request $request, Apartment $apartment): RedirectResponse
    {
        $this->authorizeApartment($apartment);

        if (! $apartment->cashBoxes()->exists()) {
            return redirect()->route('apartments.wizard.cash-box', $apartment);
        }

        $validated = $request->validate([
            'units' => ['required', 'array'],
            'units.*.floor' => ['nullable', 'string', 'max:50'],
            'units.*.block' => ['nullable', 'string', 'max:50'],
            'units.*.square_meters' => ['nullable', 'numeric', 'min:0'],
            'units.*.share_coefficient' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($validated['units'] as $unitId => $data) {
            Unit::where('id', $unitId)
                ->where('apartment_id', $apartment->id)
                ->update([
                    'floor' => $data['floor'] ?? null,
                    'block' => $data['block'] ?? null,
                    'square_meters' => $data['square_meters'] ?? null,
                    'share_coefficient' => $data['share_coefficient'] ?? null,
                ]);
        }

        $apartment->update(['setup_units_completed_at' => now()]);

        return redirect()->route('apartments.wizard.categories', $apartment)->with('status', 'Daire bilgileri kaydedildi.');
    }

    public function skipUnits(Apartment $apartment): RedirectResponse
    {
        $this->authorizeApartment($apartment);

        if (! $apartment->cashBoxes()->exists()) {
            return redirect()->route('apartments.wizard.cash-box', $apartment);
        }

        $apartment->update(['setup_units_completed_at' => now()]);

        return redirect()->route('apartments.wizard.categories', $apartment)->with('status', 'Daire adımı atlandı.');
    }

    public function categoriesStep(Apartment $apartment): RedirectResponse|View
    {
        $this->authorizeApartment($apartment);

        if (! $apartment->cashBoxes()->exists()) {
            return redirect()->route('apartments.wizard.cash-box', $apartment);
        }

        if ($apartment->setup_units_completed_at === null) {
            return redirect()->route('apartments.wizard.units', $apartment);
        }

        if ($apartment->setup_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        $categories = $apartment->categories()->orderBy('name')->get();

        return view('apartments.wizard.categories', [
            'apartment' => $apartment,
            'categories' => $categories,
            'activeStep' => 4,
        ]);
    }

    public function storeCategory(Request $request, Apartment $apartment): RedirectResponse
    {
        $this->authorizeApartment($apartment);

        if (! $apartment->cashBoxes()->exists()) {
            return redirect()->route('apartments.wizard.cash-box', $apartment);
        }

        if ($apartment->setup_units_completed_at === null) {
            return redirect()->route('apartments.wizard.units', $apartment);
        }

        if ($apartment->setup_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->where(fn ($query) => $query->where('apartment_id', $apartment->id)),
            ],
            'type' => ['required', 'in:all,income,expense'],
        ]);

        Category::create([
            'apartment_id' => $apartment->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'is_active' => true,
            'is_system' => false,
        ]);

        return redirect()->route('apartments.wizard.categories', $apartment)->with('status', 'Kategori eklendi.');
    }

    public function finish(Request $request, Apartment $apartment, CurrentApartment $currentApartment): RedirectResponse
    {
        $this->authorizeApartment($apartment);

        if (! $apartment->cashBoxes()->exists()) {
            return redirect()->route('apartments.wizard.cash-box', $apartment);
        }

        if ($apartment->setup_units_completed_at === null) {
            return redirect()->route('apartments.wizard.units', $apartment);
        }

        $user = auth()->user();
        $apartment->update(['setup_completed_at' => now()]);
        $currentApartment->setFor($user, $apartment->id);

        if ($user->isAdmin()) {
            return redirect()->route('apartments.show', $apartment)->with('status', 'Kurulum tamamlandı.');
        }

        return redirect()->route('dashboard')->with('status', 'Kurulum tamamlandı.');
    }

    private function authorizeApartment(Apartment $apartment): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $member = $apartment->members()->withPivot('role')->whereKey($user->id)->first();
        abort_unless($member && $member->pivot->role === 'owner', 403);
    }

    private function redirectForCompletedSteps(Apartment $apartment): ?RedirectResponse
    {
        if ($apartment->setup_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        if ($apartment->cashBoxes()->exists()) {
            if ($apartment->setup_units_completed_at === null) {
                return redirect()->route('apartments.wizard.units', $apartment);
            }

            return redirect()->route('apartments.wizard.categories', $apartment);
        }

        return null;
    }
}
