<?php

namespace App\Support\Paytr;

use App\Models\User;

class Access
{
    public function isTestAccount(?User $user): bool
    {
        return $user !== null && $user->activated_at !== null && $user->deactivated_at === null
            && in_array(strtolower(trim($user->email)), config('paytr.test_account_emails', []), true);
    }

    public function canSettleTest(?User $user): bool
    {
        if (! config('paytr.allow_test_settlement')) {
            return false;
        }
        if (app()->environment('production')) {
            return config('paytr.test_accounts_only') && ! config('registration.enabled') && $this->isTestAccount($user);
        }

        return app()->environment(['local', 'testing', 'staging'])
            && (! config('paytr.test_accounts_only') || $this->isTestAccount($user));
    }

    public function canStart(?User $user): bool
    {
        if (! config('payments.card_enabled') || ! $user) {
            return false;
        }
        if (config('paytr.test_accounts_only')) {
            return config('paytr.test_mode') && $this->isTestAccount($user) && $this->canSettleTest($user);
        }
        if (app()->environment('production') && config('paytr.test_mode')) {
            return false;
        }

        return true;
    }
}
