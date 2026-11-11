<?php

namespace App\Enums;

enum ExpenseRelation: string
{
    case Unassigned = 'unassigned';
    case Aidat = 'aidat';
    case MalikPayi = 'malik_payi';

    public function label(): string
    {
        return match ($this) {
            self::Unassigned => 'Henüz Belirlenmedi',
            self::Aidat => 'Aidat Kapsamında',
            self::MalikPayi => 'Kat Maliki Payı Kapsamında',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
