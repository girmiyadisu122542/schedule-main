<?php

namespace App\Models\Role;

use App\Models\User;
use Helper\Cache\PermissionCacheHandler;
use Helper\Field\Field;
use Helper\Model\BaseModel;
use Helper\Permission\RoleBasedQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends BaseModel {
    use HasFactory, SoftDeletes, RoleBasedQuery;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'is_system',
        'state',
        'user_id',
        'description',
        'unique_per_user',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {
        static::created(fn ($role) => PermissionCacheHandler::updateRoleCache());
        static::updated(fn ($role) => PermissionCacheHandler::updateRoleCache());
        static::deleted(fn ($role) => PermissionCacheHandler::updateRoleCache());

        parent::booted();
    }

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function rolePermissions(): HasMany {
        return $this->hasMany(RolePermission::class);
    }

    public function userRoleBindings(): HasMany {
        return $this->hasMany(UserRoleBinding::class);
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::state(),
            Field::isSystem(),
            Field::uniquePerUser(),
            Field::name('name__localized'),
            field::description('description_localized'),
            Field::isUniquePerUser('unique_per_user')->asBool(),
            Field::permissionsCount(fn ($role) => count($role->rolePermissions ?? [])),
            Field::usersCount()->asInt(),
            Field::permissions(function ($role) {
                $fields = [
                    Field::id('permission.id'),
                    Field::name('permission.name__localized'),
                ];

                return $role->rolePermissions->collection($fields);
            }),
        ];
    }

    /**
     * Fields for the roles management table. Same as indexFields but without the
     * per-role permission list — the table only shows the counts, so this avoids
     * loading every role's permissions (permissions_count comes from withCount).
     *
     * @return array
     */
    public function listFields(): array {
        return [
            Field::id(),
            Field::state(),
            Field::isSystem(),
            Field::uniquePerUser(),
            Field::name('name__localized'),
            Field::description('description_localized'),
            Field::isUniquePerUser('unique_per_user')->asBool(),
            Field::permissionsCount()->asInt(),
            Field::usersCount()->asInt(),
        ];
    }

    public function idAndNameFields(): array {
        return [
            Field::name('name__localized'),
            Field::id(),
        ];
    }
}
