<?php

namespace App\Models\User;

use Helper\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserOTPCode extends BaseModel {
    use SoftDeletes;

    /**
     * No updated_at — OTP codes are append-only
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_2fa_id', 'code', 'expires_at', 'used_at',
    ];

    /**
     * Relationship Section
     */
    public function user2FA(): BelongsTo {
        return $this->belongsTo(User2FA::class);
    }
}
