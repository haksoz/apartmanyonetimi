<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'apartment_id',
        'account_id',
        'due_id',
        'amount',
        'payment_date',
        'method',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];
}
