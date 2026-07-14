<?php

namespace App\Models\Permission;

use App\Models\User;
use Helper\Field\Field;
use Helper\Traits\JsonbTrait;
use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model {
    use ModelTrait, JsonbTrait, HasFactory;

    /**
     * the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'level',
        'state',
        'is_group',
        'user_id',
        'permission_group_id',
    ];

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function permissionGroup(): BelongsTo {
        return $this->belongsTo(PermissionGroup::class);
    }

    public function permissions(): HasMany {
        return $this->hasMany(Permission::class);
    }

    public function permissionGroups(): HasMany {
        return $this->hasMany(PermissionGroup::class);
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::level(),
            Field::code(),
            Field::isGroup(),
            Field::permissionGroupId(),
            Field::name('name__localized'),
            Field::permissionsCount()->asInt(),
            Field::createdAt(fn ($data) => $data?->created_at?->format(DATE_FORMAT)),
            Field::parent(function ($data) {
                $parent = $data->permissionGroup;
                if (!$parent) {
                    return null;
                }

                $fields = [
                    Field::id(),
                    Field::name('name__localized'),
                    Field::level(),
                ];

                return $parent->resource($fields);
            }),
            Field::children(function ($permissionGroup) {
                $fields = [
                    Field::id(),
                    Field::name('name__localized'),
                    Field::isGroup(),
                ];

                return $permissionGroup->permissionGroups->collection($fields);
            }),
        ];
    }

    public function dropdown(): array {
        return [
            Field::id(),
            Field::name('name__localized'),
        ];
    }

    public function rolePermissionFields(array $grantedIds): array {
        $fields = [
           Field::id(),
           Field::key(),
           Field::name('name__localized'),
           Field::isPermitted(fn ($permission) => in_array($permission->id, $grantedIds)),
        ];

        return [
            Field::id(),
            Field::name('name__localized'),
            Field::code(),
            Field::level(),
            Field::permissionGroupId(),
            Field::makeCollection('permissions', fields: $fields),
        ];
    }
}
