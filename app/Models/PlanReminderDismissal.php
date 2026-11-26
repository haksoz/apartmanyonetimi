<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanReminderDismissal extends Model
{
    protected $fillable = ['user_id', 'apartment_id', 'plan_type', 'plan_id', 'period'];
}
