<?php

namespace App\Models\Role;

use App\Models\Permission\Permission;
use App\Models\User;
use Helper\Cache\PermissionCacheHandler;
use Helper\Field\Field;
use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RolePermission extends Model {
    use SoftDeletes, ModelTrait, HasFactory;

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'permission_id',
        'role_id',
        'user_id',
        'state',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {
        static::created(fn ($rolePermission) => PermissionCacheHandler::updateCache());
        static::updated(fn ($rolePermission) => PermissionCacheHandler::updateCache());
        static::deleted(fn ($rolePermission) => PermissionCacheHandler::updateCache());

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

    public function permission(): BelongsTo {
        return $this->belongsTo(Permission::class);
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::name(),
            Field::roleId(),
            Field::permissionId(),
        ];
    }
}
