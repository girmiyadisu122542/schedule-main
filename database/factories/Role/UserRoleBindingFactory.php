<?php

namespace Database\Factories\Role;

use App\Models\Role\UserRoleBinding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRoleBinding>
 */
class UserRoleBindingFactory extends Factory {
    protected $model = UserRoleBinding::class;

    public function definition(): array {
        return [
            'starts_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'ends_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'user_id' => null,
            'role_id' => null,
            'assigned_by' => User::first()->id,
        ];
    }
}
