<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $user_id
 * @property string $kind
 * @property string $label
 * @property string|null $subtitle
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class UserPaymentMethod extends Model
{
    protected $table = 'user_payment_methods';

    protected $fillable = [
        'uuid',
        'user_id',
        'kind',
        'label',
        'subtitle',
        'is_default',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'kind' => 'string',
        'label' => 'string',
        'subtitle' => 'string',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

