<?php

namespace App\Models\Permission;

use App\Models\Role\RolePermission;
use App\Models\Role\UserPermissionOverride;
use App\Models\User;
use Helper\Cache\PermissionCacheHandler;
use Helper\Field\Field;
use Helper\Traits\JsonbTrait;
use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model {
    use ModelTrait, JsonbTrait, HasFactory;

    /**
     * the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'allowed_roles' => 'array',
        'is_system' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key', 'name', 'state', 'user_id',
        'permission_group_id', 'unique_per_user', 'allowed_roles', 'is_system',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {
        static::created(fn ($permission) => PermissionCacheHandler::updateCache());
        static::updated(fn ($permission) => PermissionCacheHandler::updateCache());
        static::deleted(fn ($permission) => PermissionCacheHandler::updateCache());

        parent::booted();
    }

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function permissionGroup(): BelongsTo {
        return $this->belongsTo(PermissionGroup::class);
    }

    public function rolePermissions(): HasMany {
        return $this->hasMany(RolePermission::class);
    }

    public function userPermissionOverrides(): HasMany {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::key(),
            Field::state(),
            Field::name('name__localized'),
            Field::permissionGroupId(),

            Field::isSystem(),
            Field::uniquePerUser(),
            Field::isUniquePerUser('unique_per_user')->asBool(),

            Field::permissionGroup(function ($data) {
                $fields = [
                    Field::id(),
                    Field::name('name__localized'),
                ];

                return $data->permissionGroup?->resource($fields);
            }),
        ];
    }
}
