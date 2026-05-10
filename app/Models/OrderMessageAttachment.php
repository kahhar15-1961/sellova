<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMessageAttachment extends Model
{
    protected $table = 'order_message_attachments';

    protected $fillable = [
        'uuid',
        'chat_message_id',
        'order_id',
        'uploaded_by_user_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'attachment_kind',
        'visibility',
        'scan_status',
        'scan_completed_at',
    ];

    protected $casts = [
        'chat_message_id' => 'integer',
        'order_id' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'size_bytes' => 'integer',
        'scan_completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
