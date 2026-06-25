<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'trial_package_id',
        'trial_duration_months',
        'fallback_package_id',
    ];

    public function trialPackage()
    {
        return $this->belongsTo(Package::class, 'trial_package_id');
    }

    public function fallbackPackage()
    {
        return $this->belongsTo(Package::class, 'fallback_package_id');
    }

    public static function getTrialPackage()
    {
        $setting = self::first();
        if (!$setting || !$setting->trial_package_id) {
            return Package::where('slug', 'standart')->first();
        }
        return $setting->trialPackage;
    }

    public static function getTrialDuration()
    {
        $setting = self::first();
        return $setting ? $setting->trial_duration_months : 2;
    }

    public static function getFallbackPackage()
    {
        $setting = self::first();
        if (!$setting || !$setting->fallback_package_id) {
            return Package::where('slug', 'baslangic')->first();
        }
        return $setting->fallbackPackage;
    }
}
