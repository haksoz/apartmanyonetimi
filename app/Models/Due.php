<?php

namespace App\Models;

use App\Enums\DueType;
use Illuminate\Validation\ValidationException;
use App\Models\Traits\HasReferenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Due extends Model
{
    use HasReferenceNumber, SoftDeletes;

    // Compatibility for legacy model callers; no independent month is persisted.
    private ?string $legacyPeriod = null;

    public function setPeriodAttribute($value): void
    {
        $this->legacyPeriod = $value ? \Carbon\Carbon::parse($value)->format('Y-m') : null;
    }

    public function getPeriodAttribute(): ?string
    {
        return $this->record_date?->format('Y-m');
    }

    public function getRecordDateAttribute(): ?\Carbon\Carbon
    {
        return $this->created_at_manual ?? $this->created_at;
    }

    public function scopeForMonth($query, string $month)
    {
        $date = \Carbon\Carbon::createFromFormat('!Y-m', $month);
        return $query->recordDatesBetween($date->toDateString(), $date->copy()->endOfMonth()->toDateString());
    }

    public function scopeRecordDatesBetween($query, string $from, string $to)
    {
        return $query->whereRaw('DATE(COALESCE(dues.created_at_manual, dues.created_at)) >= ?', [$from])
            ->whereRaw('DATE(COALESCE(dues.created_at_manual, dues.created_at)) <= ?', [$to]);
    }

    public function scopeBeforeRecordDate($query, string $date)
    {
        return $query->whereRaw('DATE(COALESCE(dues.created_at_manual, dues.created_at)) < ?', [$date]);
    }
    protected $fillable = [
        'apartment_id',
        'due_batch_id',
        'unit_id',
        'account_id',
        'due_type',
        'category_id',
        'reference_number',
        'period',
        'amount',
        'remaining_amount',
        'due_date',
        'status',
        'description',
        'created_at_manual',
        'is_imported',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'due_date' => 'date',
        'created_at_manual' => 'date',
        'is_imported' => 'boolean',
        'due_type' => DueType::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Due $due) {
            $due->due_type ??= DueType::Aidat;
            $due->created_at_manual ??= $due->legacyPeriod ? $due->legacyPeriod.'-01' : today()->toDateString();
        });

        static::updating(function (Due $due) {
            if ($due->isDirty('due_type')) {
                throw ValidationException::withMessages(['due_type' => 'Kaydedilmiş borcun türü değiştirilemez. Yanlış tür için mevcut iptal/düzeltme sürecini kullanın.']);
            }
        });
    }

    public function getTargetingRuleLabelAttribute(): string
    {
        return match ($this->due_type) {
            DueType::Aidat => 'Kiracı Öncelikli',
            DueType::MalikPayi => 'Sadece Kat Maliki',
            default => 'Belirlenmemiş',
        };
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DueBatch::class, 'due_batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function getDueTypeLabelAttribute(): string
    {
        return $this->due_type?->label() ?? '-';
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->is_imported) {
            return 'İçe Aktarma';
        }

        if ($this->batch?->plan) {
            return 'Aidat Planı: '.$this->batch->plan->name;
        }

        if ($this->batch?->assessment_plan_id) {
            return 'Taksitli Borç Planı';
        }

        if ($this->batch?->source_type === DueBatch::SOURCE_INDIVIDUAL) {
            return 'Birebir Borçlandırma';
        }

        return $this->batch ? 'Toplu Borçlandırma' : 'Manuel';
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(AccountTransaction::class, 'transactionable');
    }

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function getComputedStatusAttribute(): string
    {
        if ($this->remaining_amount == 0) {
            return 'paid';
        }
        if ($this->remaining_amount < $this->amount) {
            return 'partial';
        }
        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }
        return 'pending';
    }

    protected function getReferencePrefix(): string
    {
        return 'BRC';
    }
}
