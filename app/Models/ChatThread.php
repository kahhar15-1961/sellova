<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatThread extends Model
{
    protected $table = 'chat_threads';

    protected $fillable = [
        'uuid',
        'kind',
        'order_id',
        'buyer_user_id',
        'seller_user_id',
        'subject',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'buyer_user_id' => 'integer',
        'seller_user_id' => 'integer',
        'kind' => 'string',
        'status' => 'string',
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }
}

