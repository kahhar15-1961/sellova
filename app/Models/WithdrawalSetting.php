<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $minimum_withdrawal_amount
 * @property string $currency
 */
class WithdrawalSetting extends Model
{
    protected $table = 'withdrawal_settings';

    protected $fillable = [
        'minimum_withdrawal_amount',
        'currency',
    ];

    protected $casts = [
        'minimum_withdrawal_amount' => 'decimal:4',
    ];
}
