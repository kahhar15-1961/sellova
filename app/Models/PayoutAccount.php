<?php

namespace App\Models;

use App\Models\Concerns\TransactionSensitive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $seller_profile_id
 * @property string $account_type
 * @property string|null $provider
 * @property string|null $account_ref_token
 * @property bool $is_default
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SellerProfile|null $seller_profile
 */
class PayoutAccount extends Model
{
    use TransactionSensitive;

    protected $table = 'payout_accounts';

    protected $fillable = [
        'seller_profile_id',
        'account_type',
        'provider',
        'account_ref_token',
        'is_default',
        'status',
    ];

    protected $casts = [
        'seller_profile_id' => 'integer',
        'account_type' => 'string',
        'is_default' => 'boolean',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction-sensitive model: use explicit DB transactions and row-level locks
     * for state transitions and financial mutations.
     */

    public function seller_profile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }
}
