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

        // Aidat durumu — tüm kategoriler (tüm zamanlar) — remaining_amount ve due_date'e göre
        $allDues = Due::where('apartment_id', $id)
            ->select('amount', 'remaining_amount', 'due_date')
            ->get();

        $dueUnpaid = $duePaid = $duePartial = $dueOverdue = 0.0;
        $today = now()->startOfDay();

        foreach ($allDues as $due) {
            $remaining = (float) $due->remaining_amount;
            $amount = (float) $due->amount;
            $isPastDue = $due->due_date && $due->due_date->startOfDay()->lt($today);

            if ($remaining == 0) {
                $duePaid += $amount;
            } elseif ($isPastDue && $remaining > 0) {
                $dueOverdue += $amount;
            } elseif ($remaining >= $amount) {
                $dueUnpaid += $amount;
            } else {
                $duePartial += $amount;
            }
        }

        // Aidat durumu — kategori bazında (tüm zamanlar) — remaining_amount ve due_date'e göre
        $duesWithCategories = Due::where('dues.apartment_id', $id)
            ->with('category')
            ->select('id', 'category_id', 'amount', 'remaining_amount', 'due_date')
            ->get();

        // [cat_id => ['name'=>..., 'paid'=>..., 'unpaid'=>..., 'partial'=>..., 'overdue'=>...]]
        $dueByCat = [];
        foreach ($duesWithCategories as $due) {
            if (!$due->category) continue;

            $catId = $due->category_id;
            $catName = $due->category->name;
            $amount = (float) $due->amount;
            $remaining = (float) $due->remaining_amount;
            $isPastDue = $due->due_date && $due->due_date->startOfDay()->lt($today);

            if (!isset($dueByCat[$catId])) {
                $dueByCat[$catId] = ['name' => $catName, 'paid' => 0, 'unpaid' => 0, 'partial' => 0, 'overdue' => 0];
            }

            if ($remaining == 0) {
                $dueByCat[$catId]['paid'] += $amount;
            } elseif ($isPastDue && $remaining > 0) {
                $dueByCat[$catId]['overdue'] += $amount;
            } elseif ($remaining >= $amount) {
                $dueByCat[$catId]['unpaid'] += $amount;
            } else {
                $dueByCat[$catId]['partial'] += $amount;
            }
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
            'dueUnpaid', 'duePaid', 'duePartial', 'dueOverdue',
            'dueByCat',
            'expenseByCategory', 'totalExpenses',
            'expensePaid', 'expenseUnpaid',
            'cashBalance', 'cashIncome', 'cashExpense',
            'monthLabels', 'monthDueData', 'monthExpData',
            'accountTypes'
        ));
    }
}
