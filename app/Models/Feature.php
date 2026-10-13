<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    public const ACCESS_FREE = 'free';

    public const ACCESS_PAID = 'paid';

    protected $fillable = [
        'key',
        'name',
        'access',
    ];
}
