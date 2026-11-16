<?php

namespace App\Support;

use App\Models\User;
use App\Support\Paytr\Access;

class CheckoutPaymentMethods
{
    public function forUser(User $user): array
    {
        $methods = [];
        if (app(Access::class)->canStart($user)) $methods[] = 'kredi_kartı';
        if ($user->bank_transfer_allowed) $methods[] = 'havale';

        return $methods;
    }
}
