<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $accounts = Account::query()
            ->with('unit')
            ->withSum(['transactions as debit_total' => function ($query) {
                $query->where('type', 'debit');
            }], 'amount')
            ->withSum(['transactions as credit_total' => function ($query) {
                $query->where('type', 'credit');
            }], 'amount')
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->orderByRaw('unit_id IS NULL, unit_id')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('accounts.index', compact('accounts', 'apartment'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get();

        return view('accounts.create', compact('apartment', 'units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());

        if (! $apartment && $currentApartment->hasAvailableFor($request->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in([Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_RESIDENT, Account::TYPE_SUPPLIER])],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('apartment_id', $apartment->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'balance' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'move_in_date' => ['required_if:type,'.Account::TYPE_TENANT, 'nullable', 'date'],
            'account_opening_date' => ['required_if:type,'.Account::TYPE_SUPPLIER, 'nullable', 'date'],
        ]);

        if ($validated['type'] === Account::TYPE_TENANT && empty($validated['unit_id'])) {
            return back()->withErrors(['unit_id' => 'Kiracı hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_TENANT && TenantAssignment::where('unit_id', $validated['unit_id'])->whereNull('move_out_date')->exists()) {
            return back()->withErrors(['unit_id' => 'Bu dairede aktif kiracı var. Önce mevcut kiracıya çıkış tarihi girin.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && empty($validated['unit_id'])) {
            return back()->withErrors(['unit_id' => 'Kat maliki hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && Account::where('unit_id', $validated['unit_id'])->where('type', Account::TYPE_OWNER)->exists()) {
            return back()->withErrors(['unit_id' => 'Bu daireye bağlı kat maliki hesabı zaten var. Mevcut kat maliki hesabını düzenleyin.'])->withInput();
        }

        DB::transaction(function () use ($apartment, $request, $validated) {
            $account = Account::create([
                'apartment_id' => $apartment->id,
                'unit_id' => in_array($validated['type'], [Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_RESIDENT], true) ? ($validated['unit_id'] ?? null) : null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'balance' => $validated['balance'] ?? 0,
                'account_opening_date' => $validated['type'] === Account::TYPE_SUPPLIER ? $validated['account_opening_date'] : null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($account->type === Account::TYPE_TENANT && $account->unit_id) {
                TenantAssignment::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $account->unit_id,
                    'account_id' => $account->id,
                    'move_in_date' => $validated['move_in_date'],
                ]);

                Unit::whereKey($account->unit_id)->update(['occupant_account_id' => $account->id]);
            }

            if ($account->type === Account::TYPE_OWNER && $account->unit_id) {
                Unit::whereKey($account->unit_id)->update(['owner_account_id' => $account->id]);
            }
        });

        return redirect()->route('accounts.index')->with('status', 'Hesap oluşturuldu.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->with([
                'unit',
                'transactions',
                'dues' => fn ($query) => $query->where('status', 'unpaid')->orderBy('due_date'),
            ])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $debit = (float) $account->transactions->where('type', 'debit')->sum('amount');
        $credit = (float) $account->transactions->where('type', 'credit')->sum('amount');

        return view('accounts.show', compact('account', 'debit', 'credit'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $units = Unit::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->orderBy('unit_no')
            ->get();

        return view('accounts.edit', compact('account', 'apartment', 'units'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());

        if (! $apartment && $currentApartment->hasAvailableFor($request->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $validated = $request->validate([
            'type' => ['required', Rule::in([Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_RESIDENT, Account::TYPE_SUPPLIER])],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('apartment_id', $account->apartment_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'balance' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'move_in_date' => ['required_if:type,'.Account::TYPE_TENANT, 'nullable', 'date'],
            'move_out_date' => ['nullable', 'date', 'after_or_equal:move_in_date'],
            'account_opening_date' => ['required_if:type,'.Account::TYPE_SUPPLIER, 'nullable', 'date'],
        ]);

        if ($validated['type'] === Account::TYPE_TENANT && empty($validated['unit_id'])) {
            return back()->withErrors(['unit_id' => 'Kiracı hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_TENANT && ! empty($validated['unit_id'])) {
            $hasOtherActiveTenant = TenantAssignment::where('unit_id', $validated['unit_id'])
                ->whereNull('move_out_date')
                ->where('account_id', '!=', $account->id)
                ->exists();

            if ($hasOtherActiveTenant) {
                return back()->withErrors(['unit_id' => 'Bu dairede aktif kiracı var. Önce mevcut kiracıya çıkış tarihi girin.'])->withInput();
            }
        }

        if ($validated['type'] === Account::TYPE_OWNER && empty($validated['unit_id'])) {
            return back()->withErrors(['unit_id' => 'Kat maliki hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && ! empty($validated['unit_id'])) {
            $hasOtherOwner = Account::where('unit_id', $validated['unit_id'])
                ->where('type', Account::TYPE_OWNER)
                ->whereKeyNot($account->id)
                ->exists();

            if ($hasOtherOwner) {
                return back()->withErrors(['unit_id' => 'Bu daireye bağlı kat maliki hesabı zaten var. Mevcut kat maliki hesabını düzenleyin.'])->withInput();
            }
        }

        DB::transaction(function () use ($account, $request, $validated) {
            $account->update([
                'unit_id' => in_array($validated['type'], [Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_RESIDENT], true) ? ($validated['unit_id'] ?? null) : null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'balance' => $validated['balance'] ?? 0,
                'account_opening_date' => $validated['type'] === Account::TYPE_SUPPLIER ? $validated['account_opening_date'] : null,
                'is_active' => $request->boolean('is_active'),
            ]);

            if ($account->type === Account::TYPE_TENANT && $account->unit_id) {
                $assignment = TenantAssignment::firstOrNew([
                    'account_id' => $account->id,
                    'move_out_date' => null,
                ]);

                $assignment->fill([
                    'apartment_id' => $account->apartment_id,
                    'unit_id' => $account->unit_id,
                    'move_in_date' => $validated['move_in_date'],
                    'move_out_date' => $validated['move_out_date'] ?? null,
                ])->save();

                Unit::whereKey($account->unit_id)->update([
                    'occupant_account_id' => empty($validated['move_out_date'])
                        ? $account->id
                        : Unit::find($account->unit_id)?->owner_account_id,
                ]);
            }

            if ($account->type === Account::TYPE_OWNER && $account->unit_id) {
                Unit::whereKey($account->unit_id)->update(['owner_account_id' => $account->id]);
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Hesap güncellendi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $account = Account::query()
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Hesap silindi.');
    }
}
