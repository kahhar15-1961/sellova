<?php

namespace App\Models;

use App\Auth\RoleCodes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password_hash
 * @property string $status
 * @property string $risk_level
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserRole> $userRoles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserRole> $assignedUserRoles
 * @property-read \App\Models\SellerProfile|null $sellerProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\KycVerification> $kycVerifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Cart> $carts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderStateTransition> $orderStateTransitions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EscrowEvent> $escrowEvents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Wallet> $wallets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WalletLedgerBatch> $walletLedgerBatches
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WithdrawalRequest> $withdrawalRequests
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisputeCase> $disputeCases
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisputeEvidence> $disputeEvidences
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisputeDecision> $disputeDecisions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog> $auditLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 */
class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'uuid',
        'email',
        'phone',
        'password_hash',
        'status',
        'risk_level',
        'last_login_at',
    ];

    protected $casts = [
        'status' => 'string',
        'risk_level' => 'string',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    /**
     * {@see UserRole} rows where this user recorded {@code assigned_by} (distinct from roles held).
     */
    public function assignedUserRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'assigned_by');
    }

    public function hasRoleCode(string $code): bool
    {
        return $this->roles()->where('roles.code', $code)->exists();
    }

    public function isPlatformStaff(): bool
    {
        return $this->hasRoleCode(RoleCodes::Admin) || $this->hasRoleCode(RoleCodes::Adjudicator);
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class, 'user_id');
    }

    public function kycVerifications(): HasMany
    {
        return $this->hasMany(KycVerification::class, 'reviewed_by');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'buyer_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_user_id');
    }

    public function orderStateTransitions(): HasMany
    {
        return $this->hasMany(OrderStateTransition::class, 'actor_user_id');
    }

    public function escrowEvents(): HasMany
    {
        return $this->hasMany(EscrowEvent::class, 'actor_user_id');
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'user_id');
    }

    public function walletLedgerBatches(): HasMany
    {
        return $this->hasMany(WalletLedgerBatch::class, 'created_by');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'reviewed_by');
    }

    public function disputeCases(): HasMany
    {
        return $this->hasMany(DisputeCase::class, 'opened_by_user_id');
    }

    public function disputeEvidences(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class, 'submitted_by_user_id');
    }

    public function disputeDecisions(): HasMany
    {
        return $this->hasMany(DisputeDecision::class, 'decided_by_user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'buyer_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, "user_roles");
    }
}
