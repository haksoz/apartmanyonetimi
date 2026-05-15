<?php

namespace App\Models;

use App\Models\Traits\HasReferenceNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransaction extends Model
{
    use SoftDeletes, HasReferenceNumber;

    protected $fillable = [
        'apartment_id',
        'cash_box_id',
        'account_id',
        'category_id',
        'type',
        'description',
        'amount',
        'transaction_date',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected function getReferencePrefix(): string
    {
        return 'CT';
    }

    public function generateReferenceNumber(): string
    {
        $apartmentCode = $this->getApartmentCode();
        $prefix = $this->getReferencePrefix();
        $now = Carbon::now();
        $yearMonth = $now->format('Ym'); // 202505 formatında

        $pattern = $prefix . '-' . $apartmentCode . '-' . $yearMonth . '%';

        // Get the last number for this apartment and year-month
        $lastRef = static::query()
            ->where('apartment_id', $this->apartment_id)
            ->where('reference_number', 'like', $pattern)
            ->orderBy('reference_number', 'desc')
            ->value('reference_number');

        if ($lastRef) {
            $lastNum = (int) substr($lastRef, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('%s-%s-%s%04d', $prefix, $apartmentCode, $yearMonth, $nextNum);
    }
}
