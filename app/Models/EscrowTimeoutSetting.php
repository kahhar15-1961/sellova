<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscrowTimeoutSetting extends Model
{
    protected $table = 'escrow_timeout_settings';

    protected $fillable = [
        'unpaid_order_expiration_minutes',
        'unpaid_order_warning_minutes',
        'seller_fulfillment_deadline_hours',
        'seller_fulfillment_warning_hours',
        'buyer_review_deadline_hours',
        'buyer_review_reminder_1_hours',
        'buyer_review_reminder_2_hours',
        'escalation_warning_minutes',
        'seller_min_fulfillment_hours',
        'seller_max_fulfillment_hours',
        'buyer_min_review_hours',
        'buyer_max_review_hours',
        'auto_escalation_after_review_expiry',
        'auto_cancel_unpaid_orders',
        'auto_release_after_buyer_timeout',
        'auto_create_dispute_on_timeout',
        'dispute_review_queue_enabled',
        'updated_by_user_id',
    ];

    protected $casts = [
        'auto_escalation_after_review_expiry' => 'boolean',
        'auto_cancel_unpaid_orders' => 'boolean',
        'auto_release_after_buyer_timeout' => 'boolean',
        'auto_create_dispute_on_timeout' => 'boolean',
        'dispute_review_queue_enabled' => 'boolean',
    ];
}
