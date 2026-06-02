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
        $prefix        = $this->getReferencePrefix();
        $year          = Carbon::now()->format('y'); // 2-digit year: 25

        $pattern = $prefix . '-' . $apartmentCode . '-' . $year . '-%';

        // Silinmiş kayıtları da dahil et (soft delete + unique constraint çakışmasını önler)
        $query = static::query()
            ->where('apartment_id', $this->apartment_id)
            ->where('reference_number', 'like', $pattern);

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            $query = $query->withTrashed();
        }

        $lastRef = $query
            ->orderByRaw('CAST(SUBSTRING_INDEX(reference_number, "-", -1) AS UNSIGNED) DESC')
            ->value('reference_number');

        if ($lastRef) {
            $lastNum = (int) substr($lastRef, strrpos($lastRef, '-') + 1);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('%s-%s-%s-%05d', $prefix, $apartmentCode, $year, $nextNum);
    }

    protected function getApartmentCode(): string
    {
        $apartment = $this->apartment;

        return $apartment?->code ?? 'XXXX';
    }

    abstract protected function getReferencePrefix(): string;
}
