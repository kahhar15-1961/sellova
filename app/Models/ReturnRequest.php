<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $table = 'return_requests';

    protected $fillable = [
        'uuid',
        'rma_code',
        'order_id',
        'order_item_id',
        'buyer_user_id',
        'seller_user_id',
        'reason_code',
        'notes',
        'evidence_json',
        'status',
        'resolution_code',
        'reverse_logistics_status',
        'return_tracking_url',
        'return_carrier',
        'refund_status',
        'refund_amount',
        'refund_submitted_at',
        'refunded_at',
        'decision_note',
        'decided_by_user_id',
        'requested_at',
        'sla_due_at',
        'escalated_at',
        'decided_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'order_item_id' => 'integer',
        'buyer_user_id' => 'integer',
        'seller_user_id' => 'integer',
        'evidence_json' => 'array',
        'requested_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'escalated_at' => 'datetime',
        'refund_amount' => 'decimal:4',
        'refund_submitted_at' => 'datetime',
        'refunded_at' => 'datetime',
        'decided_at' => 'datetime',
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

    public function events(): HasMany
    {
        return $this->hasMany(ReturnRequestEvent::class, 'return_request_id');
    }
}

