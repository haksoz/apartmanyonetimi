<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSubscription>
 */
class UserSubscriptionFactory extends Factory
{
    protected $model = UserSubscription::class;

    public function definition(): array
    {
        $period = fake()->randomElement(['monthly', 'yearly']);
        $package = Package::factory()->create();

        return [
            'user_id' => User::factory(),
            'package_id' => $package->id,
            'period' => $period,
            'price' => $period === 'yearly' ? $package->yearly_price : $package->monthly_price,
            'started_at' => now(),
            'expires_at' => $period === 'yearly' ? now()->addYear() : now()->addMonth(),
            'is_active' => true,
        ];
    }
}
