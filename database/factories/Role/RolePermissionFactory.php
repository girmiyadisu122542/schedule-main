<?php

namespace Database\Factories\Role;

use App\Models\Role\RolePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory {
    protected $model = RolePermission::class;

    public function definition(): array {
        return [
            'state' => STATE_ACTIVE,
            'user_id' => null,
            'role_id' => null,
            'permission_id' => null,
        ];
    }
}
