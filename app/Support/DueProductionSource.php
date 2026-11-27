<?php

namespace App\Support;

use App\Models\Due;
use App\Models\DueBatch;

enum DueProductionSource: string
{
    case Individual = 'individual';
    case ManualBatch = 'manual_batch';
    case FixedBatch = 'fixed_batch';
    case ExpenseBatch = 'expense_batch';
    case Consumption = 'consumption';
    case AidatPlan = 'aidat_plan';
    case AssessmentPlan = 'assessment_plan';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Birebir borçlandırma',
            self::ManualBatch => 'Manuel toplu borçlandırma',
            self::FixedBatch => 'Sabit tutarlı toplu borçlandırma',
            self::ExpenseBatch => 'Gider bazlı toplu borçlandırma',
            self::Consumption => 'Tüketim bazlı borçlandırma',
            self::AidatPlan => 'Aidat Planı',
            self::AssessmentPlan => 'Taksitli Borç Planı',
            self::Import => 'İçe Aktarma',
        };
    }

    public static function forRecordedDue(Due $due): ?self
    {
        if ($due->production_source !== null) {
            return self::tryFrom($due->production_source);
        }

        if ($due->is_imported) {
            return self::Import;
        }

        $batch = $due->batch;
        if (! $batch || $batch->apartment_id !== $due->apartment_id) {
            return null;
        }

        return match (true) {
            $batch->consumption_period_id !== null => self::Consumption,
            $batch->assessment_plan_id !== null => self::AssessmentPlan,
            $batch->due_plan_id !== null => self::AidatPlan,
            default => match ($batch->source_type) {
                DueBatch::SOURCE_INDIVIDUAL => self::Individual,
                DueBatch::SOURCE_MANUAL => self::ManualBatch,
                DueBatch::SOURCE_FIXED => self::FixedBatch,
                DueBatch::SOURCE_EXPENSES => self::ExpenseBatch,
                default => null,
            },
        };
    }

    public static function billing(string $source, string $distribution): self
    {
        if ($distribution === DueBatch::DISTRIBUTION_CONSUMPTION) {
            return self::Consumption;
        }

        return match ($source) {
            DueBatch::SOURCE_INDIVIDUAL => self::Individual,
            DueBatch::SOURCE_MANUAL => self::ManualBatch,
            DueBatch::SOURCE_FIXED => self::FixedBatch,
            DueBatch::SOURCE_EXPENSES => self::ExpenseBatch,
        };
    }
}
