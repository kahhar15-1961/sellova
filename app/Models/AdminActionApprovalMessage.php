<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActionApprovalMessage extends Model
{
    public $timestamps = false;

    protected $table = 'admin_action_approval_messages';

    protected $fillable = [
        'uuid',
        'approval_id',
        'author_user_id',
        'message',
        'created_at',
        'delivered_at',
    ];

    protected $casts = [
        'approval_id' => 'integer',
        'author_user_id' => 'integer',
        'created_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(AdminActionApproval::class, 'approval_id');
    }

    public function author_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
