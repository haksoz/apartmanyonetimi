<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'description' => fake()->sentence(),
            'apartment_limit' => fake()->numberBetween(1, 20),
            'monthly_price' => fake()->randomFloat(2, 0, 500),
            'yearly_price' => fake()->randomFloat(2, 0, 5000),
            'is_active' => true,
            'show_on_website' => true,
            'is_trial' => false,
            'sort_order' => 0,
        ];
    }
}
