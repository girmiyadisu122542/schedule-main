<?php

namespace Database\Factories\Permission;

use App\Models\Permission\Permission;
use App\Models\Permission\PermissionGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Translation\Back\English;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory {
    protected $model = Permission::class;

    public function definition(): array {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => [English::getKey() => $name],
            'key' => strtolower(str_replace(' ', '_', $name)),
            'unique_per_user' => fake()->boolean(),
            'state' => STATE_ACTIVE,
            'is_system' => false,
            'allowed_roles' => [],
            'user_id' => User::first()->id,
            'permission_group_id' => PermissionGroup::first()?->id ?? PermissionGroup::factory(),
        ];
    }
}
