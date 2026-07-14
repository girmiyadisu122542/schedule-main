<?php

namespace App\Models\Otp;

use Helper\Model\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Otp extends BaseModel {
    use HasFactory;

    protected $fillable = ['user_id', 'otp_code', 'type', 'method', 'expires_at', 'verified_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
