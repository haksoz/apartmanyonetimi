<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\DuePlan;
use App\Models\Expense;
use App\Support\AidatPeriodReconciliation;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(CurrentApartment $currentApartment, AidatPeriodReconciliation $aidatReconciliation)
    {
        $user = auth()->user();
        $apartment = $currentApartment->getFor($user);

        if (! $apartment && $currentApartment->hasAvailableFor($user)) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('onboarding.show');
        }

        if ($nextStep = $apartment->nextSetupStep()) {
            return redirect()->route('apartments.wizard.'.$nextStep, $apartment)
                ->with('status', 'Lütfen apartman kurulumunu tamamlayın.');
        }

        $id = $apartment->id;

        // --- Özet rakamlar ---
        $totalUnits    = Unit::where('apartment_id', $id)->count();
        $totalAccounts = Account::where('apartment_id', $id)->where('is_active', true)->where('is_hidden', false)->count();

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
                $dueOverdue += $remaining;
            } elseif ($remaining >= $amount) {
                $dueUnpaid += $amount;
            } else {
                $duePartial += $remaining;
            }
        }

        // Aidat durumu — tür (due_type) bazında (tüm zamanlar) — remaining_amount ve due_date'e göre
        $duesWithTypes = Due::where('dues.apartment_id', $id)
            ->select('id', 'due_type', 'amount', 'remaining_amount', 'due_date')
            ->get();

        // [type_value => ['name'=>..., 'paid'=>..., 'unpaid'=>..., 'partial'=>..., 'overdue'=>...]]
        $dueByType = [];
        foreach ($duesWithTypes as $due) {
            if (!$due->due_type) continue;

            $typeValue = $due->due_type->value;
            $typeName = $due->due_type->label();
            $amount = (float) $due->amount;
            $remaining = (float) $due->remaining_amount;
            $isPastDue = $due->due_date && $due->due_date->startOfDay()->lt($today);

            if (!isset($dueByType[$typeValue])) {
                $dueByType[$typeValue] = ['name' => $typeName, 'paid' => 0, 'unpaid' => 0, 'partial' => 0, 'overdue' => 0];
            }

            if ($remaining == 0) {
                $dueByType[$typeValue]['paid'] += $amount;
            } elseif ($isPastDue && $remaining > 0) {
                $dueByType[$typeValue]['overdue'] += $remaining;
            } elseif ($remaining >= $amount) {
                $dueByType[$typeValue]['unpaid'] += $amount;
            } else {
                $dueByType[$typeValue]['partial'] += $remaining;
            }
        }

        // Toplam gider (tüm zamanlar)
        $totalExpenses = (float) Expense::where('apartment_id', $id)->sum('amount');

        // Gider kategorileri (tüm zamanlar) — category_id ilişkisi önce, yoksa eski string sütunu
        $expenseByCategory = Expense::where('expenses.apartment_id', $id)
            ->leftJoin('categories', function ($join) {
                $join->on('categories.id', '=', 'expenses.category_id')
                     ->whereNull('categories.deleted_at');
            })
            ->selectRaw("COALESCE(NULLIF(categories.name,''), NULLIF(expenses.category,''), 'Diğer') as cat, SUM(expenses.amount) as total")
            ->groupBy('cat')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'cat');

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
            ->where('is_hidden', false)
            ->selectRaw("type, COUNT(*) as cnt")
            ->groupBy('type')
            ->pluck('cnt', 'type');

        // Tahsil edilmemiş toplam aidat (bekleyen + gecikmiş + kısmi ödenmiş kalan)
        $uncollectedDues = $dueUnpaid + $dueOverdue + $duePartial;
        $partialAidatConfirmation = null;
        $plan = DuePlan::query()
            ->where('apartment_id', $id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        if ($plan && $this->isOwnerOf($apartment) && now()->day >= $plan->generate_day) {
            $period = now()->format('Y-m');
            $reconciliation = $aidatReconciliation->reconcile($plan, $period);
            $completedCount = count($reconciliation['completed_account_ids']);
            $targetCount = $reconciliation['target_accounts']->count();

            if ($completedCount > 0 && $completedCount < $targetCount) {
                $partialAidatConfirmation = [
                    'plan' => $plan,
                    'period' => $period,
                    'completed_count' => $completedCount,
                    'missing_count' => $targetCount - $completedCount,
                ];
            }
        }

        return view('dashboard', compact(
            'apartment',
            'totalUnits', 'totalAccounts',
            'dueUnpaid', 'duePaid', 'duePartial', 'dueOverdue',
            'dueByType',
            'expenseByCategory', 'totalExpenses',
            'expensePaid', 'expenseUnpaid',
            'cashBalance', 'cashIncome', 'cashExpense',
            'monthLabels', 'monthDueData', 'monthExpData',
            'accountTypes',
            'uncollectedDues',
            'partialAidatConfirmation'
        ));
    }
}
