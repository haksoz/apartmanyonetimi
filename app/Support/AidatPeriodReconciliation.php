<?php

namespace App\Support;

use App\Enums\DueType;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Due;
use App\Models\DuePlan;
use App\Models\TenantAssignment;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AidatPeriodReconciliation
{
    public function categoryFor(Apartment $apartment): Category
    {
        return Category::query()->firstOrCreate(
            [
                'apartment_id' => $apartment->id,
                'name' => 'Aidat',
            ],
            [
                'type' => Category::TYPE_INCOME,
                'is_active' => true,
                'is_system' => true,
            ],
        );
    }

    public function reconcile(DuePlan $plan, string $period): array
    {
        $periodDate = Carbon::parse($period.'-01');
        $category = $this->categoryFor($plan->apartment);
        $targetAccounts = $this->targetAccounts($plan, $periodDate);
        $completedAccountIds = Due::query()
            ->where('apartment_id', $plan->apartment_id)
            ->where('period', $period)
            ->where('due_type', DueType::Aidat->value)
            ->where('category_id', $category->id)
            ->whereIn('account_id', $targetAccounts->pluck('account.id'))
            ->pluck('account_id')
            ->unique()
            ->all();
        $missingAccounts = $targetAccounts
            ->reject(fn (array $item) => in_array($item['account']->id, $completedAccountIds, true))
            ->values();

        return [
            'category' => $category,
            'target_accounts' => $targetAccounts,
            'completed_account_ids' => $completedAccountIds,
            'missing_accounts' => $missingAccounts,
        ];
    }

    public function targetAccounts(DuePlan $plan, Carbon $periodDate, ?Collection $units = null): Collection
    {
        $units ??= Unit::query()
            ->with(['ownerAccount', 'accounts'])
            ->where('apartment_id', $plan->apartment_id)
            ->orderBy('unit_no')
            ->get();

        return $units->map(function (Unit $unit) use ($plan, $periodDate) {
            $account = $this->accountForPeriod($unit, $periodDate, $plan->target_audience);
            if (! $account) {
                return null;
            }

            $weight = match ($plan->distribution_type) {
                DuePlan::DISTRIBUTION_SQUARE_METERS => (float) ($unit->square_meters ?? 0),
                DuePlan::DISTRIBUTION_SHARE_COEFFICIENT => (float) ($unit->share_coefficient ?? 0),
                default => 1,
            };

            if ($weight <= 0 && $plan->distribution_type !== DuePlan::DISTRIBUTION_EQUAL) {
                return null;
            }

            return ['unit' => $unit, 'account' => $account, 'weight' => $weight];
        })->filter()->values();
    }

    public function accountForPeriod(Unit $unit, Carbon $periodDate, string $targetAudience): ?Account
    {
        if ($targetAudience === 'owner_only') {
            return $unit->ownerAccount;
        }

        $tenantAssignment = TenantAssignment::query()
            ->where('unit_id', $unit->id)
            ->where('move_in_date', '<=', $periodDate->copy()->endOfMonth())
            ->where(fn ($query) => $query->whereNull('move_out_date')->orWhere('move_out_date', '>=', $periodDate->copy()->startOfMonth()))
            ->with('account')
            ->first();

        return $tenantAssignment?->account ?? $unit->ownerAccount;
    }

    public function hasExistingAidatDue(Apartment $apartment, int $accountId, string $period, ?int $excludeDueId = null): bool
    {
        $category = $this->categoryFor($apartment);

        $query = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->where('due_type', DueType::Aidat->value)
            ->where('category_id', $category->id);

        if ($excludeDueId) {
            $query->where('id', '!=', $excludeDueId);
        }

        return $query->exists();
    }
}
