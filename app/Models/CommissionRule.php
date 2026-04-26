<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string $scope_type
 * @property int $scope_id
 * @property string $rule_type
 * @property array $rule_json
 * @property int $priority
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $effective_to
 */
class CommissionRule extends Model
{
    protected $table = 'commission_rules';

    protected $fillable = [
        'uuid',
        'scope_type',
        'scope_id',
        'rule_type',
        'rule_json',
        'priority',
        'effective_from',
        'effective_to',
        'is_active',
        'effective_to',
    ];

    protected $casts = [
        'scope_type' => 'string',
        'scope_id' => 'integer',
        'rule_type' => 'string',
        'rule_json' => 'array',
        'priority' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // No direct Eloquent relationships.
}
