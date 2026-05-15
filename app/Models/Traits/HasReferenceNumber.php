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
        $apartmentCode = $this->getApartmentCode();
        $prefix = $this->getReferencePrefix();
        $now = Carbon::now();
        $yearMonth = $now->format('Y-m');

        $pattern = $apartmentCode . '-' . $prefix . '-' . $yearMonth . '%';

        // Get the last number for this apartment, prefix and year-month
        $lastRef = static::query()
            ->where('apartment_id', $this->apartment_id)
            ->where('reference_number', 'like', $pattern)
            ->orderBy('reference_number', 'desc')
            ->value('reference_number');

        if ($lastRef) {
            $lastNum = (int) substr($lastRef, -6);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('%s-%s-%s%06d', $apartmentCode, $prefix, $yearMonth, $nextNum);
    }

    protected function getApartmentCode(): string
    {
        $apartment = $this->apartment;

        if (! $apartment) {
            return 'XX';
        }

        // Get first letter of each word in apartment name
        $words = explode(' ', $apartment->name);
        $code = '';

        foreach ($words as $word) {
            if (! empty($word)) {
                $code .= strtoupper(substr($word, 0, 1));
                if (strlen($code) >= 2) {
                    break;
                }
            }
        }

        // Pad with X if less than 2 characters
        return str_pad($code, 2, 'X');
    }

    abstract protected function getReferencePrefix(): string;
}
