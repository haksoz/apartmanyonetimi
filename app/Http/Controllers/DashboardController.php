<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('onboarding.show');
        }

        $id = $apartment->id;

        // --- Özet rakamlar ---
        $totalUnits    = Unit::where('apartment_id', $id)->count();
        $totalAccounts = Account::where('apartment_id', $id)->where('is_active', true)->count();

        // Aidat durumu — tüm kategoriler (tüm zamanlar)
        $dueStats = Due::where('apartment_id', $id)
            ->selectRaw("status, SUM(amount) as total, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('total', 'status');

        $dueUnpaid  = (float) ($dueStats['unpaid']  ?? 0);
        $duePaid    = (float) ($dueStats['paid']     ?? 0);
        $duePartial = (float) ($dueStats['partial']  ?? 0);

        // Aidat durumu — kategori bazında (tüm zamanlar)
        $dueCategories = \App\Models\Category::where('apartment_id', $id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $dueByCategoryAndStatus = Due::where('dues.apartment_id', $id)
            ->join('categories', 'dues.category_id', '=', 'categories.id')
            ->selectRaw("categories.id as cat_id, categories.name as cat_name, dues.status, SUM(dues.amount) as total")
            ->groupBy('categories.id', 'categories.name', 'dues.status')
            ->get();

        // [cat_id => ['name'=>..., 'paid'=>..., 'unpaid'=>..., 'partial'=>...]]
        $dueByCat = [];
        foreach ($dueByCategoryAndStatus as $row) {
            if (!isset($dueByCat[$row->cat_id])) {
                $dueByCat[$row->cat_id] = ['name' => $row->cat_name, 'paid' => 0, 'unpaid' => 0, 'partial' => 0];
            }
            $dueByCat[$row->cat_id][$row->status] += (float) $row->total;
        }

        // Gider kategorileri (tüm zamanlar)
        $expenseByCategory = Expense::where('apartment_id', $id)
            ->selectRaw("COALESCE(NULLIF(category,''), 'Diğer') as cat, SUM(amount) as total")
            ->groupBy('cat')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'cat');

        $totalExpenses = $expenseByCategory->sum();

        // Gider ödeme durumu (tüm zamanlar)
        $expensePaid   = (float) Expense::where('apartment_id', $id)->where('is_paid', true)->sum('amount');
        $expenseUnpaid = (float) Expense::where('apartment_id', $id)->where('is_paid', false)->sum('amount');

        // Kasa
        $cashIncome  = (float) CashTransaction::where('apartment_id', $id)->where('type', 'income')->sum('amount');
        $cashExpense = (float) CashTransaction::where('apartment_id', $id)->where('type', 'expense')->sum('amount');
        $cashBalance = $cashIncome - $cashExpense;

        // Son 6 ay aylık aidat tahakkuku
        $monthlyDues = Due::where('apartment_id', $id)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Son 6 ay aylık gider
        $monthlyExpenses = Expense::where('apartment_id', $id)
            ->where('expense_date', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // 6 aylık etiket listesi
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        $monthLabels   = $months->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->translatedFormat('M Y'))->values();
        $monthDueData  = $months->map(fn ($m) => (float) ($monthlyDues[$m]  ?? 0))->values();
        $monthExpData  = $months->map(fn ($m) => (float) ($monthlyExpenses[$m] ?? 0))->values();

        // Hesap tipi dağılımı
        $accountTypes = Account::where('apartment_id', $id)
            ->where('is_active', true)
            ->selectRaw("type, COUNT(*) as cnt")
            ->groupBy('type')
            ->pluck('cnt', 'type');

        return view('dashboard', compact(
            'apartment',
            'totalUnits', 'totalAccounts',
            'dueUnpaid', 'duePaid', 'duePartial',
            'dueByCat',
            'expenseByCategory', 'totalExpenses',
            'expensePaid', 'expenseUnpaid',
            'cashBalance', 'cashIncome', 'cashExpense',
            'monthLabels', 'monthDueData', 'monthExpData',
            'accountTypes'
        ));
    }
}
