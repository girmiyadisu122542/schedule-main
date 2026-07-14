<?php

namespace App\Models\User;

use App\Models\User;
use Helper\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User2FA extends BaseModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'type', 'secret', 'is_primary', 'enabled', 'verified_at',
    ];

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function otpCodes(): HasMany {
        return $this->hasMany(UserOTPCode::class);
    }
}
