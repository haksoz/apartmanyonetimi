<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Explicitly applied to admin overview queries; never payment processing or customer access. */
class AdminTestData
{
    public static function apply(Builder $query): void
    {
        if (SystemSetting::query()->value('show_test_customers')) {
            return;
        }

        $table = $query->getModel()->getTable();
        $users = fn () => DB::table('users')->select('id')->where('is_test_customer', true);
        // The founder remains stable when management changes; a test resident never hides a real building.
        $apartments = fn () => DB::table('apartments')->select('id')->whereIn('user_id', $users());
        $orders = fn () => DB::table('user_subscriptions')->select('id')->whereIn('user_id', $users());

        match ($table) {
            'users' => $query->where('users.is_test_customer', false),
            'apartments' => $query->whereNotIn('apartments.id', $apartments()),
            'user_subscriptions', 'checkout_journeys', 'quote_requests' => $query->where(fn ($q) => $q
                ->whereNull($table.'.user_id')->orWhereNotIn($table.'.user_id', $users())),
            'subscription_payment_attempts' => $query->whereNotIn($table.'.subscription_id', $orders()),
            'subscriptions' => $query
                ->where(fn ($q) => $q->whereNull('subscriptions.apartment_id')->orWhereNotIn('subscriptions.apartment_id', $apartments()))
                ->where(fn ($q) => $q->whereNull('subscriptions.retained_user_id')->orWhereNotIn('subscriptions.retained_user_id', $users())),
            'subscription_items' => $query->whereNotIn($table.'.subscription_id', $orders())
                ->where(fn ($q) => $q->whereNull($table.'.apartment_id')->orWhereNotIn($table.'.apartment_id', $apartments())),
            'apartment_data_operations' => $query
                ->where(fn ($q) => $q->whereNull($table.'.user_id')->orWhereNotIn($table.'.user_id', $users()))
                ->where(fn ($q) => $q->whereNull($table.'.source_apartment_id')->orWhereNotIn($table.'.source_apartment_id', $apartments())),
            default => null,
        };
    }
}
