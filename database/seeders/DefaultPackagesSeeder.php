<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class DefaultPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Başlangıç',
                'slug' => 'baslangic',
                'description' => 'Tek apartman için temel yönetim paketi.',
                'apartment_limit' => 1,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Standart',
                'slug' => 'standart',
                'description' => 'Profesyonel yöneticiler için 5 apartman.',
                'apartment_limit' => 5,
                'monthly_price' => 199,
                'yearly_price' => 1990,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Profesyonel',
                'slug' => 'profesyonel',
                'description' => 'Büyük ölçekli yönetim şirketleri için 20 apartman.',
                'apartment_limit' => 20,
                'monthly_price' => 499,
                'yearly_price' => 4990,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            Package::firstOrCreate(['slug' => $package['slug']], $package);
        }
    }
}
