<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'apartment_limit',
        'multi_apartment_limit',
        'monthly_price',
        'yearly_price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'apartment_limit' => 'integer',
        'multi_apartment_limit' => 'integer',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(PackageFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function hasFeature(string $key): bool
    {
        return $this->features()
            ->where('feature_key', $key)
            ->where('is_enabled', true)
            ->exists();
    }
}
