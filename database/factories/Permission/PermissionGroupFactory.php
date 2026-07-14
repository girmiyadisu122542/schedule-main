<?php

namespace Database\Factories\Permission;

use App\Models\Permission\PermissionGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionGroup>
 */
class PermissionGroupFactory extends Factory {
    protected $model = PermissionGroup::class;

    public function definition(): array {
        return [
            'name' => fake()->words(2, true),
            'is_group' => fake()->boolean(),
            'code' => strtoupper(fake()->unique()->bothify('PG-###??')),
            'level' => fake()->unique()->numberBetween(10, 100),
            'user_id' => User::first()->id,
            'permission_group_id' => null,
        ];
    }
}
