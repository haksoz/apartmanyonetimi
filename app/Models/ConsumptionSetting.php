<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumptionSetting extends Model
{
    public const TYPES = ['cold_water' => 'Soğuk Su', 'hot_water' => 'Sıcak Su', 'heating' => 'Isınma', 'electricity' => 'Elektrik'];

    public const METHODS = ['index' => 'Endeks farkı', 'direct' => 'Doğrudan dönemsel tüketim girişi'];

    public const UNITS = ['cold_water' => 'm³', 'hot_water' => 'm³', 'heating' => 'Pay birimi', 'electricity' => 'kWh'];

    protected $fillable = ['apartment_id', 'consumption_type', 'measurement_unit', 'method'];

    public static function forType(int $apartmentId, string $type): self
    {
        return static::where('apartment_id', $apartmentId)->where('consumption_type', $type)->first()
            ?? new static(['apartment_id' => $apartmentId, 'consumption_type' => $type,
                'measurement_unit' => self::UNITS[$type], 'method' => $type === 'heating' ? 'direct' : 'index']);
    }
}
