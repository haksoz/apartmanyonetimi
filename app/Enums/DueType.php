<?php

namespace App\Enums;

enum DueType: string
{
    case Aidat          = 'aidat';
    case MalikPayi      = 'malik_payi';
    case MasrafYansitma = 'masraf_yansitma';
    case Ceza           = 'ceza';
    case Faiz           = 'faiz';

    public function label(): string
    {
        return match ($this) {
            self::Aidat          => 'Aidat',
            self::MalikPayi      => 'Malik Payı',
            self::MasrafYansitma => 'Masraf Yansıtma',
            self::Ceza           => 'Ceza',
            self::Faiz           => 'Faiz',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public static function batchOptions(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], [
            self::Aidat, self::MalikPayi, self::MasrafYansitma,
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
