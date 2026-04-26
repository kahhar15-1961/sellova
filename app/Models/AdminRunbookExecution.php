<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRunbookExecution extends Model
{
    protected $table = 'admin_runbook_executions';

    protected $fillable = [
        'incident_id',
        'runbook_id',
        'started_by_user_id',
        'completed_by_user_id',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'incident_id' => 'integer',
        'runbook_id' => 'integer',
        'started_by_user_id' => 'integer',
        'completed_by_user_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(AdminEscalationIncident::class, 'incident_id');
    }

    public function runbook(): BelongsTo
    {
        return $this->belongsTo(AdminRunbook::class, 'runbook_id');
    }

    public function started_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function completed_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AdminRunbookStepExecution::class, 'execution_id');
    }
}
