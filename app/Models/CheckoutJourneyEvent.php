<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutJourneyEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['details' => 'array'];
}
