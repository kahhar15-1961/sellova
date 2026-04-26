<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRunbookStepExecution extends Model
{
    protected $table = 'admin_runbook_step_executions';

    protected $fillable = [
        'execution_id',
        'runbook_step_id',
        'completed_by_user_id',
        'status',
        'evidence_notes',
        'completed_at',
    ];

    protected $casts = [
        'execution_id' => 'integer',
        'runbook_step_id' => 'integer',
        'completed_by_user_id' => 'integer',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(AdminRunbookExecution::class, 'execution_id');
    }

    public function runbook_step(): BelongsTo
    {
        return $this->belongsTo(AdminRunbookStep::class, 'runbook_step_id');
    }

    public function completed_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
