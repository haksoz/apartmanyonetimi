<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class BillingProfile extends Model
{
    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_CORPORATE = 'corporate';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'label',
        'party_type',
        'legal_name',
        'identity_number',
        'tax_office',
        'email',
        'phone',
        'country',
        'province',
        'district',
        'address',
        'postal_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Uygulama seviyesinde açık türler. Veritabanı enum tutmaz.
     *
     * @return array<string, string>
     */
    public static function partyTypes(): array
    {
        return [
            self::TYPE_INDIVIDUAL => 'Kişi',
            self::TYPE_CORPORATE => 'Kurum',
            self::TYPE_OTHER => 'Diğer',
        ];
    }

    public static function partyLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return static::partyTypes()[$type] ?? $type;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'party_type' => ['required', 'string', 'max:32', Rule::in(array_keys(static::partyTypes()))],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'identity_number' => ['nullable', 'string', 'max:64'],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Sipariş açıldığı andaki kopya. Profil daha sonra değişse de bu dizi yeniden yazılmaz.
     *
     * @return array<string, mixed>
     */
    public function orderSnapshot(): array
    {
        return [
            'billing_profile_id' => $this->id,
            'billing_party_type' => $this->party_type,
            'billing_label' => $this->label,
            'billing_legal_name' => $this->legal_name,
            'billing_identity_number' => $this->identity_number,
            'billing_tax_office' => $this->tax_office,
            'billing_email' => $this->email,
            'billing_phone' => $this->phone,
            'billing_country' => $this->country,
            'billing_province' => $this->province,
            'billing_district' => $this->district,
            'billing_address' => $this->address,
            'billing_postal_code' => $this->postal_code,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }
}
