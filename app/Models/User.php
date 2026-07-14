<?php

namespace App\Models;

use App\Models\Role\Role;
use App\Models\Role\UserPermissionOverride;
use App\Models\Role\UserRoleBinding;
use App\Models\User\UserDetail;
use App\Models\User\UserFieldsTrait;
use App\Models\User\UserLog;
use Helper\Cache\RoleCacheHandler;
use Helper\Model\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Translation\Back\English;

class User extends UserModel {
    use UserFieldsTrait;

    /**
     * Get the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'full_name' => 'array',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name', 'phone', 'email', 'photo', 'state', 'user_id',
        'user_detail_id', 'mfa_enabled', 'cover_photo',
    ];

    /**
     * Robust localized display name -- never blank: the first non-empty localized
     * `full_name` value, otherwise the email local-part, otherwise a #id label.
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string {
        $resolved = $this->full_name__localized;

        if (is_string($resolved) && trim($resolved) !== '') {
            return $resolved;
        }

        $emailLocal = $this->email
            ? Str::before($this->email, '@')
            : '';

        return $emailLocal !== ''
            ? $emailLocal
            : 'User ' . hashLabel($this->id);
    }

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function userDetail(): BelongsTo {
        return $this->belongsTo(UserDetail::class);
    }

    public function userRoleBindings(): HasMany {
        return $this->hasMany(UserRoleBinding::class);
    }
    public function userPermissionOverrides(): HasMany {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function userLogs(): HasMany {
        return $this->hasMany(UserLog::class);
    }

    public function roles(): BelongsToMany {
        return $this->belongsToMany(Role::class, UserRoleBinding::getTableName());
    }

    public function isLoggedIn(): bool {
        return $this->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get all permissions for the user (role-based + overrides)
     *
     * @return array
     */
    public function getAllPermissions(): array {
        return RoleCacheHandler::getUserPermissionLists(
            user: $this
        );
    }

    /**
    * A user is treated as a super admin when they hold the Super Admin system
    * role through a role binding that is currently within its active date
    * range.
    *
    * @return bool
    */
    public function isSuperAdmin(): bool {
        return $this->userRoleBindings()
            ->withInActiveDateRange()
            ->whereHas('role', function ($query) {
                $query
                    ->where('name->' . English::getKey(), SUPER_ADMIN_ROLE_NAME)
                    ->where('is_system', true);
            })
            ->exists();
    }
}
