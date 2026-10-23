<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ResidentAccountVisibility
{
    public static function transactions(Builder|Relation $query, Account $account): Builder|Relation
    {
        if (! $account->residenceHasEnded()) {
            return $query;
        }

        // Payments settling the resident's old debt remain visible after departure.
        if ($account->residence_started_at) {
            $query->whereDate('transaction_date', '>=', $account->residence_started_at);
        }
        if ($account->account_end_date) {
            $query->where(fn ($q) => $q->where('type', 'credit')->orWhereDate('transaction_date', '<=', $account->account_end_date));
        }

        return $query;
    }
}
