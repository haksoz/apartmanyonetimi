<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApartmentManagementPeriod extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime', 'is_current' => 'boolean'];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function durationLabel(): string
    {
        $end = $this->ended_at ?? ($this->is_current ? now() : null);
        if (! $this->started_at || ! $end || $end->lt($this->started_at)) {
            return '—';
        }
        $minutes = (int) floor($this->started_at->diffInMinutes($end));
        if ($minutes >= 1440) {
            return intdiv($minutes, 1440).' gün '.intdiv($minutes % 1440, 60).' saat';
        }
        if ($minutes >= 60) {
            return intdiv($minutes, 60).' saat '.($minutes % 60).' dakika';
        }

        return $minutes > 0 ? $minutes.' dakika' : '1 dakikadan az';
    }
}
