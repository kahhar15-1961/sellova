<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestEvent extends Model
{
    public $timestamps = false;

    protected $table = 'return_request_events';

    protected $fillable = [
        'return_request_id',
        'event_code',
        'actor_user_id',
        'meta_json',
        'created_at',
    ];

    protected $casts = [
        'return_request_id' => 'integer',
        'actor_user_id' => 'integer',
        'meta_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }
}

