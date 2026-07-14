<?php

namespace App\Models\Role;

use App\Models\User;
use Helper\Cache\RoleCacheHandler;
use Helper\Field\Field;
use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRoleBinding extends Model {
    use SoftDeletes,
        ModelTrait,
        HasFactory;

    /**
     * the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'conditions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'role_id',
        'assigned_by',
        'conditions',
        'starts_at',
        'ends_at',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {
        static::created(fn ($roleBinding) => RoleCacheHandler::updateUserCacheFromBinding($roleBinding));
        static::updated(fn ($roleBinding) => RoleCacheHandler::updateUserCacheFromBinding($roleBinding));
        static::deleted(fn ($roleBinding) => RoleCacheHandler::revokeUserRoleBindingCache($roleBinding));
        static::restored(fn ($roleBinding) => RoleCacheHandler::updateUserCacheFromBinding($roleBinding));

        parent::booted();
    }

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo {
        return $this->belongsTo(Role::class);
    }

    public function assignedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Role shape used when listing a user's role-inherited permissions.
     * The $denyOverrides collection (keyed by permission id) drives the
     * per-permission "revoked" status.
     *
     * @param \Illuminate\Support\Collection $denyOverrides
     * @return array
     */
    public function inheritedPermissionsRoleFields($denyOverrides): array {
        return [
            Field::make('role_id', fn ($binding) => $binding->role?->id),
            Field::make('role_name', fn ($binding) => $binding->role?->name__localized),
            Field::make('binding_id', fn ($binding) => $binding->id),
            Field::make('permissions', function ($binding) use ($denyOverrides) {
                return $binding->role?->rolePermissions
                    ->map(function ($rolePermission) use ($denyOverrides, $binding) {
                        $permission = $rolePermission->permission;
                        if (!$permission) {
                            return null;
                        }

                        $override = $denyOverrides->get($permission->id);

                        $fields = [
                            Field::id(),
                            Field::name('name__localized'),
                            Field::make('start_date', fn () => $binding->starts_at?->format(DATE_FORMAT)),
                            Field::make('end_date', fn () => $binding->ends_at?->format(DATE_FORMAT)),
                            Field::make('status', fn () => $override ? 'revoked' : 'allowed'),
                            Field::make('override_id', fn () => $override?->id),
                        ];

                        return $permission->resource($fields);
                    })
                    ->filter()
                    ->values()
                    ->all();
            }),
        ];
    }

    public function formFields(): array {
        return [
            Field::roleId(),
            Field::make('starts_at', fn ($data) => $data?->starts_at?->format(DATE_FORMAT)),
            Field::make('ends_at', fn ($data) => $data?->ends_at?->format(DATE_FORMAT)),
        ];
    }

    public function scopeWithInActiveDateRange(Builder $query): Builder {
        $currentTime = date(DATETIME_FORMAT);
        return $query
            ->whereEndDateIsAFterToday()
            ->where('starts_at', '<=', $currentTime);
    }

    public function scopeWhereEndDateIsAfterToday(Builder $query): Builder {
        $currentTime = date(DATETIME_FORMAT);
        return $query
            ->where(function ($query) use ($currentTime) {
                $query
                    ->orWhere('ends_at', '=', null)
                    ->orWhere('ends_at', '>', $currentTime);
            });
    }
}
