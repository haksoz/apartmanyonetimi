<?php

namespace App\Models\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait HasReferenceNumber
{
    public static function bootHasReferenceNumber(): void
    {
        static::creating(function ($model) {
            if (empty($model->reference_number)) {
                $model->reference_number = $model->generateReferenceNumber();
            }
        });
    }

    public function generateReferenceNumber(): string
    {
        $prefix = $this->getReferencePrefix();
        $now = Carbon::now();
        $yearMonth = $now->format('Y-m');

        // Get the last number for this prefix and year-month
        $lastRef = static::query()
            ->where('reference_number', 'like', $prefix . '-' . $yearMonth . '%')
            ->orderBy('reference_number', 'desc')
            ->value('reference_number');

        if ($lastRef) {
            $lastNum = (int) substr($lastRef, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('%s-%s%04d', $prefix, $yearMonth, $nextNum);
    }

    abstract protected function getReferencePrefix(): string;
}
