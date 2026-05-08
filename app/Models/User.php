<?php

namespace App\Models;

use App\Admin\AdminPermission;
use App\Auth\RoleCodes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $display_name
 * @property string|null $password_hash
 * @property string $status
 * @property string $risk_level
 * @property bool $restricted_checkout
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, UserRole> $userRoles
 * @property-read Collection<int, UserRole> $assignedUserRoles
 * @property-read SellerProfile|null $sellerProfile
 * @property-read Collection<int, KycVerification> $kycVerifications
 * @property-read Collection<int, Cart> $carts
 * @property-read Collection<int, Order> $orders
 * @property-read Collection<int, OrderStateTransition> $orderStateTransitions
 * @property-read Collection<int, EscrowEvent> $escrowEvents
 * @property-read Collection<int, Wallet> $wallets
 * @property-read Collection<int, WalletLedgerBatch> $walletLedgerBatches
 * @property-read Collection<int, WithdrawalRequest> $withdrawalRequests
 * @property-read Collection<int, DisputeCase> $disputeCases
 * @property-read Collection<int, DisputeEvidence> $disputeEvidences
 * @property-read Collection<int, DisputeDecision> $disputeDecisions
 * @property-read Collection<int, Review> $reviews
 * @property-read Collection<int, Notification> $notifications
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, UserPaymentMethod> $paymentMethods
 * @property-read Collection<int, UserWishlistItem> $wishlistItems
 */
class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'uuid',
        'email',
        'phone',
        'display_name',
        'password_hash',
        'status',
        'risk_level',
        'restricted_checkout',
        'last_login_at',
        'apple_sub',
    ];

    protected $casts = [
        'status' => 'string',
        'risk_level' => 'string',
        'restricted_checkout' => 'boolean',
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

    /**
     * Whether the user holds a given admin permission (via roles), or is super-admin.
     */
    public function hasPermissionCode(string $code): bool
    {
        if ($this->hasRoleCode(RoleCodes::SuperAdmin)) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                if ($permission->code === $code) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $codes
     */
    public function hasAnyPermissionCode(array $codes): bool
    {
        foreach ($codes as $code) {
            if ($this->hasPermissionCode($code)) {
                return true;
            }
        }

        return false;
    }

    public function isPlatformStaff(): bool
    {
        $staffRoleCodes = [
            RoleCodes::SuperAdmin,
            RoleCodes::Admin,
            RoleCodes::Adjudicator,
            RoleCodes::FinanceAdmin,
            RoleCodes::DisputeOfficer,
            RoleCodes::KycReviewer,
            RoleCodes::SupportAgent,
        ];
        foreach ($staffRoleCodes as $code) {
            if ($this->hasRoleCode($code)) {
                return true;
            }
        }

        return $this->hasPermissionCode(AdminPermission::ACCESS);
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
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(UserPaymentMethod::class, 'user_id');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(UserWishlistItem::class, 'user_id');
    }
}
