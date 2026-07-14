<?php

namespace Database\Factories\Role;

use App\Models\Role\UserPermissionOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPermissionOverride>
 */
class UserPermissionOverrideFactory extends Factory {
    protected $model = UserPermissionOverride::class;

    public function definition(): array {
        return [
            'allow' => fake()->boolean(),
            'starts_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'ends_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'user_id' => null,
            'assigned_by' => User::first()->id,
            'permission_id' => null,
        ];
    }
}
