<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRunbook extends Model
{
    protected $table = 'admin_runbooks';

    protected $fillable = [
        'queue_code',
        'title',
        'objective',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(AdminRunbookStep::class, 'runbook_id')->orderBy('step_order');
    }
}
