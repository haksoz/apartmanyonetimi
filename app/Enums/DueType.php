<?php

namespace App\Enums;

enum DueType: string
{
    case Aidat          = 'aidat';
    case MalikPayi      = 'malik_payi';

    public function label(): string
    {
        return match ($this) {
            self::Aidat          => 'Aidat',
            self::MalikPayi      => 'Kat Maliki Payı',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public static function batchOptions(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], [
            self::Aidat, self::MalikPayi,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function selectsCategory(): bool
    {
        return true;
    }

    public function categoryPermission(): string
    {
        return 'allow_'.$this->value;
    }

}
