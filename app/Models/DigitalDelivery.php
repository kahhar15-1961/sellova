<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigitalDelivery extends Model
{
    protected $table = 'digital_deliveries';

    protected $fillable = [
        'uuid',
        'order_id',
        'seller_user_id',
        'buyer_user_id',
        'status',
        'version',
        'external_url',
        'delivery_note',
        'files_count',
        'delivered_at',
        'buyer_confirmed_at',
        'revision_requested_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'seller_user_id' => 'integer',
        'buyer_user_id' => 'integer',
        'files_count' => 'integer',
        'delivered_at' => 'datetime',
        'buyer_confirmed_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DigitalDeliveryFile::class, 'digital_delivery_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
