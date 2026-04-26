<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string|null $code
 * @property string|null $name
 * @property string $billing_period
 * @property string $price
 * @property string|null $currency
 * @property array $benefits_json
 * @property array $commission_modifier_json
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MembershipSubscription> $membershipSubscriptions
 */
class MembershipPlan extends Model
{
    protected $table = 'membership_plans';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'billing_period',
        'price',
        'currency',
        'benefits_json',
        'commission_modifier_json',
        'is_active',
    ];

    protected $casts = [
        'billing_period' => 'string',
        'price' => 'decimal:4',
        'benefits_json' => 'array',
        'commission_modifier_json' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function membershipSubscriptions(): HasMany
    {
        return $this->hasMany(MembershipSubscription::class, 'membership_plan_id');
    }
}
