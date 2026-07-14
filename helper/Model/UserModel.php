<?php

namespace Helper\Model;

use Constants\AppConstant;
use Helper\Permission\PermissionAction;
use Helper\Permission\RoleBasedQueryForUser;
use Helper\Traits\JsonbTrait;
use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class UserModel extends Authenticatable implements OAuthenticatable {
    use HasApiTokens, HasFactory, Notifiable, ModelTrait,
        RoleBasedQueryForUser, JsonbTrait, PermissionAction;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function __construct(...$args) {
        parent::__construct(...$args);
        $this->setConnection(AppConstant::SCHEDULE_DATABASE_CONNECTION);
    }
}
