<?php

namespace Database\Factories\Role;

use App\Models\Role\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Translation\Back\English;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory {
    protected $model = Role::class;

    public function definition(): array {
        $firstUser = User::first();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => [English::getKey() => fake()->unique()->jobTitle()],
            'unique_per_user' => fake()->boolean(),
            'is_system' => fake()->boolean(),
            'state' => STATE_ACTIVE,
            'description' => [English::getKey() => fake()->sentence()],
            'user_id' => $firstUser ? $firstUser->id : 1,
        ];
    }
}
