<?php

namespace App\Models\Role;

use App\Models\Permission\Permission;
use App\Models\User;
use Helper\Cache\RoleCacheHandler;
use Helper\Field\Field;
use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPermissionOverride extends Model {
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
        'permission_id',
        'assigned_by',
        'conditions',
        'starts_at',
        'ends_at',
        'allow',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {
        static::created(fn ($permissionOverride) => RoleCacheHandler::updateUserCacheFromOverride($permissionOverride));
        static::updated(fn ($permissionOverride) => RoleCacheHandler::updateUserCacheFromOverride($permissionOverride));
        static::deleted(fn ($permissionOverride) => RoleCacheHandler::revokeUserPermissionOverrideCache($permissionOverride));
        static::restored(fn ($permissionOverride) => RoleCacheHandler::updateUserCacheFromOverride($permissionOverride));

        parent::booted();
    }

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo {
        return $this->belongsTo(Permission::class);
    }

    public function assignedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_by');
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

    /**
     * Fields that will be use when
     * Returning data to the browser
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::allow(),
            Field::conditions(),
            Field::permissionId(),
            Field::endsAt(fn ($data) => $data->ends_at?->format(DATE_FORMAT)),
            Field::makeLocalized('permission', 'permission.name', useFirst: true),
            Field::startsAt(fn ($data) => $data->starts_at?->format(DATE_FORMAT)),
            Field::makeResource('assigned_by', 'assignedBy', fields: 'idAndNameFields'),
        ];
    }

    /**
     * Minimal shape returned after writing a deny-override (revoke).
     *
     * @return array
     */
    public function denyOverrideFields(): array {
        return [
            Field::id(),
            Field::allow(),
            Field::userId(),
            Field::permissionId(),
            Field::startsAt(fn ($data) => $data->starts_at?->format(DATE_FORMAT)),
            Field::endsAt(fn ($data) => $data->ends_at?->format(DATE_FORMAT)),
        ];
    }
}
