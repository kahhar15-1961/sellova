<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalDeliveryFile extends Model
{
    protected $table = 'digital_delivery_files';

    protected $fillable = [
        'uuid',
        'digital_delivery_id',
        'order_id',
        'uploaded_by_user_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'visibility',
        'scan_status',
        'scan_completed_at',
        'downloaded_at',
    ];

    protected $casts = [
        'digital_delivery_id' => 'integer',
        'order_id' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'size_bytes' => 'integer',
        'scan_completed_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(DigitalDelivery::class, 'digital_delivery_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
