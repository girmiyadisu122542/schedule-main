<?php

namespace Database\Factories\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Translation\Back\English;

/**
 * @extends Factory<User>
 */
class UserDetailFactory extends Factory {

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'first_name' => [
                English::getKey() => fake()->firstName(),
            ],

            'middle_name' => [
                English::getKey() => fake()->firstName(),
            ],

            'last_name' => [
                English::getKey() => fake()->lastName(),
            ],

            'birth_date' => now()->subYears(20)->format(DATE_FORMAT),
            'national_id' => fake()->unique()->numerify('################'),
            'phone' => fake()->unique()->numerify('09########'),
            'email' => fake()->unique()->safeEmail(),
            'photo' => null,
            'gender' => MALE,
            'bio' => [
                English::getKey() => fake()->sentence(),
            ],
        ];
    }
}
