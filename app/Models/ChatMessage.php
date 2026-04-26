<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    protected $fillable = [
        'uuid',
        'thread_id',
        'sender_user_id',
        'body',
        'attachment_url',
        'attachment_name',
    ];

    protected $casts = [
        'thread_id' => 'integer',
        'sender_user_id' => 'integer',
        'body' => 'string',
        'attachment_url' => 'string',
        'attachment_name' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}

