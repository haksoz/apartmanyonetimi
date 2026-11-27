<?php

namespace App\Support;

use App\Enums\DueType;
use App\Models\Apartment;
use Illuminate\Validation\ValidationException;

class LateCompensationScope
{
    public function previewEnabled(): bool
    {
        return (bool) config('late-compensation.enabled')
            || (app()->environment('local', 'testing') && config('late-compensation.preview'));
    }

    public function allows(DueProductionSource $source, DueType|string $type, ?int $categoryId = null): bool
    {
        $type = $type instanceof DueType ? $type : DueType::from($type);
        $excluded = config('late-compensation.excluded_due_types.'.$source->value, []);

        return ! in_array('*', $excluded, true) && ! in_array($type->value, $excluded, true);
    }

    public function allowsUnknownSource(DueType|string $type, ?int $categoryId = null): bool
    {
        foreach (DueProductionSource::cases() as $source) {
            if (! $this->allows($source, $type, $categoryId)) {
                return false;
            }
        }

        return true;
    }

    public function selectionAvailable(Apartment $apartment): bool
    {
        if (! $this->previewEnabled()) {
            return false;
        }

        $today = now('Europe/Istanbul')->toDateString();
        $startsOn = $apartment->late_compensation_starts_on?->toDateString();
        if ($startsOn === null || $startsOn > $today) {
            return false;
        }

        return (bool) $apartment->lateCompensationPolicies()->whereDate('effective_from', '<=', $today)
            ->latest('effective_from')->value('is_active');
    }

    public function preference(
        Apartment $apartment,
        DueProductionSource $source,
        DueType|string $type,
        ?bool $submitted,
        bool $stored = false,
        ?int $categoryId = null,
    ): bool {
        if ($submitted === null) {
            return $stored;
        }

        if (! $this->selectionAvailable($apartment) || ! $this->allows($source, $type, $categoryId)) {
            if ($submitted) {
                throw ValidationException::withMessages(['late_compensation_applies' => 'Gecikme Tazminatı bu işlemde seçilemez.']);
            }

            return $stored;
        }

        return $submitted;
    }

    public function dueAttributes(DueProductionSource $source, DueType|string $type, bool $preference = false, ?int $categoryId = null): array
    {
        return [
            'production_source' => $source->value,
            'late_compensation_applies' => $this->previewEnabled() && $preference && $this->allows($source, $type, $categoryId),
        ];
    }
}
