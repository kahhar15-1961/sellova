<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatThreadRead extends Model
{
    protected $table = 'chat_thread_reads';

    protected $fillable = [
        'thread_id',
        'user_id',
        'last_read_at',
    ];

    protected $casts = [
        'thread_id' => 'integer',
        'user_id' => 'integer',
        'last_read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

