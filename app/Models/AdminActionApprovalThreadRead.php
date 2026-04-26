<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActionApprovalThreadRead extends Model
{
    protected $table = 'admin_action_approval_thread_reads';

    protected $fillable = [
        'approval_id',
        'user_id',
        'last_read_message_id',
    ];

    protected $casts = [
        'approval_id' => 'integer',
        'user_id' => 'integer',
        'last_read_message_id' => 'integer',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(AdminActionApproval::class, 'approval_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
