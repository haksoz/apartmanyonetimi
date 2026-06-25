<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\SystemSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $standartPackage = Package::where('slug', 'standart')->first();
        $baslangicPackage = Package::where('slug', 'baslangic')->first();

        SystemSetting::updateOrCreate(
            ['id' => 1],
            [
                'trial_package_id' => $standartPackage ? $standartPackage->id : null,
                'trial_duration_months' => 2,
                'fallback_package_id' => $baslangicPackage ? $baslangicPackage->id : null,
            ]
        );
    }
}
