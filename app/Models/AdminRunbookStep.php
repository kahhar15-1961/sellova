<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRunbookStep extends Model
{
    protected $table = 'admin_runbook_steps';

    protected $fillable = [
        'runbook_id',
        'step_order',
        'instruction',
        'is_required',
        'evidence_required',
    ];

    protected $casts = [
        'runbook_id' => 'integer',
        'step_order' => 'integer',
        'is_required' => 'boolean',
        'evidence_required' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function runbook(): BelongsTo
    {
        return $this->belongsTo(AdminRunbook::class, 'runbook_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AdminRunbookStepExecution::class, 'runbook_step_id');
    }
}
