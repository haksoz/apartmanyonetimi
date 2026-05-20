<?php

namespace App\Http\Controllers;

use App\Models\Apartment;

abstract class Controller
{
    protected function isOwnerOf(?Apartment $apartment): bool
    {
        $user = auth()->user();

        if (! $user || ! $apartment) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $member = $apartment->members()
            ->withPivot('role')
            ->whereKey($user->id)
            ->first();

        return $member && $member->pivot->role === 'owner';
    }
}
