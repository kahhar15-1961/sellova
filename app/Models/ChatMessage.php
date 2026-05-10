<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    protected $fillable = [
        'uuid',
        'thread_id',
        'sender_user_id',
        'receiver_user_id',
        'sender_role',
        'body',
        'marker_type',
        'artifact_type',
        'is_delivery_proof',
        'attachment_url',
        'attachment_name',
        'attachment_type',
        'attachment_mime',
        'attachment_size',
    ];

    protected $casts = [
        'thread_id' => 'integer',
        'sender_user_id' => 'integer',
        'receiver_user_id' => 'integer',
        'sender_role' => 'string',
        'body' => 'string',
        'marker_type' => 'string',
        'artifact_type' => 'string',
        'is_delivery_proof' => 'boolean',
        'attachment_url' => 'string',
        'attachment_name' => 'string',
        'attachment_type' => 'string',
        'attachment_mime' => 'string',
        'attachment_size' => 'integer',
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

    public function escrowAttachments(): HasMany
    {
        return $this->hasMany(OrderMessageAttachment::class, 'chat_message_id');
    }
}
