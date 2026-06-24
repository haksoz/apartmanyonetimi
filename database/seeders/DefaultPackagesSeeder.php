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
                'description' => 'Küçük apartmanlar için ücretsiz başlangıç.',
                'apartment_limit' => 1,
                'multi_apartment_limit' => 0,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    '1 apartman',
                    'En fazla 12 daire',
                    'Aidat takibi',
                    'Tahsilat yönetimi',
                    'Gider ve kasa yönetimi',
                    'Hesap ekstresi ve raporlar',
                    'Otomatik aidat planlama',
                    'Kullanıcı portalı erişimi',
                    'Çoklu apartman yönetimi',
                ],
                'disabled_features' => [
                    'Otomatik aidat planlama',
                    'Kullanıcı portalı erişimi',
                    'Çoklu apartman yönetimi',
                ],
            ],
            [
                'name' => 'Standart',
                'slug' => 'standart',
                'description' => 'Tek apartmanınız için tüm özellikler.',
                'apartment_limit' => 1,
                'multi_apartment_limit' => 0,
                'monthly_price' => 300,
                'yearly_price' => 3000,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    '1 apartman',
                    '24 daire ve kullanıcı',
                    'Aidat takibi',
                    'Otomatik aidat planlama',
                    'Tahsilat yönetimi',
                    'Gider ve kasa yönetimi',
                    'Kullanıcı portalı erişimi',
                    'Hesap ekstresi ve raporlar',
                    'Çoklu apartman yönetimi',
                ],
                'disabled_features' => [
                    'Çoklu apartman yönetimi',
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Birden fazla apartman yönetenler için.',
                'apartment_limit' => 999,
                'multi_apartment_limit' => 999,
                'monthly_price' => 900,
                'yearly_price' => 9000,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'Sınırsız apartman',
                    'Sınırsız daire ve kullanıcı',
                    'Aidat takibi',
                    'Otomatik aidat planlama',
                    'Tahsilat yönetimi',
                    'Gider ve kasa yönetimi',
                    'Kullanıcı portalı erişimi',
                    'Hesap ekstresi ve raporlar',
                    'Çoklu apartman yönetimi',
                ],
                'disabled_features' => [],
            ],
        ];

        foreach ($packages as $packageData) {
            $features = $packageData['features'];
            $disabledFeatures = $packageData['disabled_features'] ?? [];
            unset($packageData['features'], $packageData['disabled_features']);

            $package = Package::updateOrCreate(
                ['slug' => $packageData['slug']],
                $packageData
            );

            // Sync features
            foreach ($features as $feature) {
                $isEnabled = !in_array($feature, $disabledFeatures);
                $package->features()->updateOrCreate(
                    ['feature_key' => $feature],
                    ['is_enabled' => $isEnabled]
                );
            }

            // Remove features that are no longer in the list
            $package->features()->whereNotIn('feature_key', $features)->delete();
        }
    }
}
