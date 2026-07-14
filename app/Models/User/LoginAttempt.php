<?php

namespace App\Models\User;

use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoginAttempt extends Model {
    use SoftDeletes, ModelTrait;
}
