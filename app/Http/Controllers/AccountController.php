<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\Payment;
use App\Support\CurrentApartment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        $filterSearch = $request->query('search');
        $filterType   = $request->query('type');
        $filterStatus = $request->query('status', 'active'); // Default: sadece aktifler

        $accounts = Account::query()
            ->with('unit')
            ->withSum(['transactions as debit_total' => function ($query) {
                $query->where('type', 'debit');
            }], 'amount')
            ->withSum(['transactions as credit_total' => function ($query) {
                $query->where('type', 'credit');
            }], 'amount')
            ->when($apartment, fn ($q) => $q->where('apartment_id', $apartment->id))
            ->when($filterSearch, fn ($q) => $q->where(function ($sub) use ($filterSearch) {
                $sub->where('accounts.name', 'like', '%' . $filterSearch . '%')
                    ->orWhereHas('unit', fn ($u) => $u->where('unit_no', 'like', '%' . $filterSearch . '%'));
            }))
            ->when($filterType,   fn ($q) => $q->where('type', $filterType))
            ->when($filterStatus === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filterStatus === 'inactive', fn ($q) => $q->where('is_active', false))
            // 'all' seçeneğinde filtre uygulanmaz
            ->orderByRaw('unit_id IS NULL, unit_id')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        $filters = compact('filterSearch', 'filterType', 'filterStatus');

        return view('accounts.index', compact('accounts', 'apartment', 'filters'));
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

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('name')
            ->get();

        return view('accounts.create', compact('apartment', 'units', 'categories'));
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

        $validator = \Validator::make($request->all(), [
            'type' => ['required', Rule::in([Account::TYPE_OWNER, Account::TYPE_TENANT, Account::TYPE_SUPPLIER])],
            'unit_id' => [
                'required_if:type,'.Account::TYPE_OWNER.','.Account::TYPE_TENANT,
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
            'default_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)],
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasyon hatası',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        if ($validated['type'] === Account::TYPE_TENANT && empty($validated['unit_id'])) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kiracı hesabı için daire bağlantısı zorunludur.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Kiracı hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_TENANT && TenantAssignment::where('unit_id', $validated['unit_id'])->whereNull('move_out_date')->exists()) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu dairede aktif kiracı var. Önce mevcut kiracıya çıkış tarihi girin.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Bu dairede aktif kiracı var. Önce mevcut kiracıya çıkış tarihi girin.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && empty($validated['unit_id'])) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kat maliki hesabı için daire bağlantısı zorunludur.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Kat maliki hesabı için daire bağlantısı zorunludur.'])->withInput();
        }

        if ($validated['type'] === Account::TYPE_OWNER && Account::where('unit_id', $validated['unit_id'])->where('type', Account::TYPE_OWNER)->where('is_active', true)->exists()) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu dairede aktif kat maliki var. Önce mevcut kat malikini pasife alın.'
                ], 422);
            }
            return back()->withErrors(['unit_id' => 'Bu dairede aktif kat maliki var. Önce mevcut kat malikini pasife alın.'])->withInput();
        }

        $account = DB::transaction(function () use ($apartment, $request, $validated) {
            $account = Account::create([
                'apartment_id' => $apartment->id,
                'unit_id' => in_array($validated['type'], [Account::TYPE_OWNER, Account::TYPE_TENANT], true) ? ($validated['unit_id'] ?? null) : null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'balance' => $validated['balance'] ?? 0,
                'account_opening_date' => $validated['type'] === Account::TYPE_SUPPLIER ? now() : null,
                'is_active' => $request->boolean('is_active', true),
                'default_category_id' => $validated['default_category_id'] ?? null,
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

            return $account;
        });

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'account' => $account,
                'message' => 'Tedarikçi oluşturuldu.'
            ]);
        }

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
                'user',
                'transactions' => fn ($query) => $query->orderBy('transaction_date')->orderBy('id'),
                'dues' => fn ($query) => $query->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date'),
                'payments' => fn ($query) => $query->where('unallocated_amount', '>', 0),
                'expenses' => fn ($query) => $query->where('is_paid', false)->orderBy('expense_date'),
            ])
            ->when($apartment, fn ($query) => $query->where('apartment_id', $apartment->id))
            ->findOrFail($id);

        // Hazır ordered transactions ile satır satır çalışan bakiye hesapla
        $transactions = $account->transactions->values();
        $running = 0;
        foreach ($transactions as $t) {
            $debit = $t->type === 'debit' ? $t->amount : 0;
            $credit = $t->type === 'credit' ? $t->amount : 0;
            $running += $debit - $credit;
            // runtime attribute for view
            $t->running_balance = $running;
        }

        // Görüntüleme: yeniden eskiye (yeniden eskiye sıralama için ters çevir)
        $transactions = $transactions->reverse()->values();

        // Ödemelere ait tahsisleri yükle ve transactionlara ekle
        $paymentIds = $transactions
            ->filter(fn($t) => ($t->transactionable_type ?? '') === Payment::class)
            ->pluck('transactionable_id')
            ->unique()
            ->values();

        if ($paymentIds->isNotEmpty()) {
            $payments = Payment::with('allocations.due')->whereIn('id', $paymentIds)->get()->keyBy('id');

            foreach ($transactions as $t) {
                if (($t->transactionable_type ?? '') === Payment::class && isset($payments[$t->transactionable_id])) {
                    $t->allocations = $payments[$t->transactionable_id]->allocations;
                } else {
                    $t->allocations = collect();
                }
            }
        } else {
            foreach ($transactions as $t) {
                $t->allocations = collect();
            }
        }

        return view('accounts.show', compact('account', 'transactions'));
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

        $categories = Category::query()
            ->where('apartment_id', $account->apartment_id)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $account->default_category_id))
            ->where(fn ($q) => $q->where('type', Category::TYPE_ALL)->orWhere('type', Category::TYPE_EXPENSE))
            ->orderBy('name')
            ->get();

        return view('accounts.edit', compact('account', 'apartment', 'units', 'categories'));
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

        // Type değiştirilemez - mevcut type'ı koru
        if ($request->has('type') && $request->input('type') !== $account->type) {
            return back()->withErrors(['type' => 'Hesap türü değiştirilemez.'])->withInput();
        }

        // Owner ve tenant için unit_id değiştirilemez
        if (in_array($account->type, [Account::TYPE_OWNER, Account::TYPE_TENANT])) {
            if ($request->has('unit_id') && (int) $request->input('unit_id') !== (int) $account->unit_id) {
                return back()->withErrors(['unit_id' => 'Kat maliki ve kiracı hesaplarında daire bağlantısı değiştirilemez.'])->withInput();
            }
        }

        $validated = $request->validate([
            'unit_id' => [
                'required_if:type,'.Account::TYPE_OWNER.','.Account::TYPE_TENANT,
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
            'account_opening_date' => ['required_if:type,'.Account::TYPE_SUPPLIER, 'nullable', 'date'],
            'default_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $account->apartment_id)],
        ]);

        // Mevcut type'ı validated'a ekle
        $validated['type'] = $account->type;

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
                ->where('is_active', true)
                ->whereKeyNot($account->id)
                ->exists();

            if ($hasOtherOwner) {
                return back()->withErrors(['unit_id' => 'Bu dairede aktif kat maliki var. Önce mevcut kat malikini pasife alın veya düzenleyin.'])->withInput();
            }
        }

        DB::transaction(function () use ($account, $request, $validated) {
            $updateData = [
                'unit_id' => in_array($validated['type'], [Account::TYPE_OWNER, Account::TYPE_TENANT], true) ? ($validated['unit_id'] ?? null) : null,
                'type' => $validated['type'],
                'balance' => $validated['balance'] ?? 0,
                'account_opening_date' => $validated['type'] === Account::TYPE_SUPPLIER ? $validated['account_opening_date'] : null,
                'is_active' => $request->boolean('is_active'),
                'default_category_id' => $validated['default_category_id'] ?? null,
            ];

            $updateData['name']  = $validated['name'];
            $updateData['phone'] = $validated['phone'] ?? null;
            $updateData['email'] = $validated['email'] ?? null;

            $account->update($updateData);

            if ($account->type === Account::TYPE_TENANT && $account->unit_id) {
                // Sadece aktif kiralamada giriş tarihi güncellenebilir (çıkış sonlandır butonu ile yapılır)
                $assignment = TenantAssignment::firstOrNew([
                    'account_id' => $account->id,
                    'move_out_date' => null,
                ]);

                $assignment->fill([
                    'apartment_id' => $account->apartment_id,
                    'unit_id' => $account->unit_id,
                    'move_in_date' => $validated['move_in_date'],
                ])->save();

                Unit::whereKey($account->unit_id)->update([
                    'occupant_account_id' => $account->id
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
     * Kiracı kiralamasını sonlandır.
     */
    public function terminateTenancy(Request $request, Account $account, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());
        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        abort_unless($account->type === Account::TYPE_TENANT, 400);

        $validated = $request->validate([
            'termination_date' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($account, $validated, $apartment) {
            // Tenant assignment'ı güncelle
            $assignment = $account->activeTenantAssignment;
            if ($assignment) {
                $assignment->update(['move_out_date' => $validated['termination_date']]);
            }

            // Unit occupant'ı eski malike geri döndür (veya null yap)
            $unit = $account->unit;
            if ($unit) {
                $unit->update(['occupant_account_id' => $unit->owner_account_id]);
            }

            // Hesabı pasifleştir ve user bağını kopar
            $account->update([
                'is_active' => false,
                'user_id' => null,
            ]);

            // User'ı apartmandan çıkar
            if ($account->user) {
                $apartment->members()->detach($account->user_id);
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Kiralama sonlandırıldı.');
    }

    /**
     * Kat maliki malikliğini sonlandır ve yeni malik ata.
     */
    public function terminateOwnership(Request $request, Account $account, CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor($request->user());
        abort_unless($apartment && $account->apartment_id === $apartment->id, 403);
        abort_unless($account->type === Account::TYPE_OWNER, 400);

        $validated = $request->validate([
            'termination_date' => ['required', 'date'],
            'new_owner_account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        DB::transaction(function () use ($account, $validated, $apartment) {
            $unit = $account->unit;
            
            // Eski maliki pasifleştir
            $account->update([
                'is_active' => false,
                'user_id' => null,
            ]);

            // User bağını kopar
            if ($account->user) {
                $apartment->members()->detach($account->user_id);
            }

            if ($validated['new_owner_account_id']) {
                // Mevcut hesabı yeni malik yap
                $newOwner = Account::findOrFail($validated['new_owner_account_id']);
                $newOwner->update([
                    'type' => Account::TYPE_OWNER,
                    'unit_id' => $unit?->id,
                    'is_active' => true,
                ]);
                if ($unit) {
                    $unit->update(['owner_account_id' => $newOwner->id]);
                }
            } else {
                // Yeni boş malik hesabı aç
                $newOwner = Account::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $unit?->id,
                    'type' => Account::TYPE_OWNER,
                    'name' => $unit ? str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT).'. Daire Kat Maliki' : 'Kat Maliki',
                    'is_active' => true,
                ]);
                if ($unit) {
                    $unit->update(['owner_account_id' => $newOwner->id]);
                }
            }

            // Dairenin occupant'ını da güncelle (eğer eski malik occupant ise)
            if ($unit && $unit->occupant_account_id === $account->id) {
                $unit->update(['occupant_account_id' => $unit->fresh()->owner_account_id]);
            }
        });

        return redirect()->route('accounts.show', $account)->with('status', 'Maliklik sonlandırıldı ve yeni malik atandı.');
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

        // Hesapta hareket var mı kontrol et
        $hasTransactions = $account->dues()->exists() ||
                          $account->transactions()->exists() ||
                          $account->expenses()->exists() ||
                          $account->payments()->exists() ||
                          $account->tenantAssignments()->exists();

        if ($hasTransactions) {
            return redirect()->back()->with('error', 'Bu hesapta hareket bulunduğu için silinemez. Önce ilişkili kayıtları silin.');
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Hesap silindi.');
    }
}
