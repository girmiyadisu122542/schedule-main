<?php

namespace Database\Factories\User;

use App\Models\User;
use App\Models\User\UserLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLog>
 */
class UserLogFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => null,
            'login_time' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'logout_time' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'ip' => $this->faker->ipv4(),
            'device' => $this->faker->randomElement(['Desktop', 'Mobile', 'Tablet']),
            'user_agent' => $this->faker->userAgent(),
            'reason' => $this->faker->optional(0.3)->randomElement([
                'User logged in',
                'User logged out',
                'Session expired',
                'Force logout',
                'Password changed',
                'Profile updated',
            ]),
        ];
    }

    /**
     * Create a user log for a specific user
     */
    public function forUser(User|int $user): static {
        $userId = $user instanceof User ? $user->id : $user;
        return $this->state(fn (array $attributes) => ['user_id' => $userId]);
    }

    /**
     * Create a login log (without logout time)
     */
    public function login(): static {
        return $this->state(fn (array $attributes) => [
            'logout_time' => null,
            'reason' => 'User logged in',
        ]);
    }

    /**
     * Create a logout log (with both login and logout times)
     */
    public function logout(): static {
        $loginTime = $this->faker->dateTimeBetween('-1 year', '-1 hour');
        return $this->state(fn (array $attributes) => [
            'login_time' => $loginTime,
            'logout_time' => $this->faker->dateTimeBetween($loginTime, 'now'),
            'reason' => 'User logged out',
        ]);
    }

    /**
     * Create a session expired log
     */
    public function sessionExpired(): static {
        $loginTime = $this->faker->dateTimeBetween('-1 year', '-1 hour');
        return $this->state(fn (array $attributes) => [
            'login_time' => $loginTime,
            'logout_time' => $this->faker->dateTimeBetween($loginTime, 'now'),
            'reason' => 'Session expired',
        ]);
    }

    /**
     * Create a force logout log
     */
    public function forceLogout(): static {
        $loginTime = $this->faker->dateTimeBetween('-1 year', '-1 hour');
        return $this->state(fn (array $attributes) => [
            'login_time' => $loginTime,
            'logout_time' => $this->faker->dateTimeBetween($loginTime, 'now'),
            'reason' => 'Force logout',
        ]);
    }

    /**
     * Create a mobile device log
     */
    public function mobile(): static {
        return $this->state(fn (array $attributes) => [
            'device' => 'Mobile',
            'user_agent' => $this->faker->mobileUserAgent(),
        ]);
    }

    /**
     * Create a desktop device log
     */
    public function desktop(): static {
        return $this->state(fn (array $attributes) => [
            'device' => 'Desktop',
            'user_agent' => $this->faker->chromeUserAgent(),
        ]);
    }

    /**
     * Create a tablet device log
     */
    public function tablet(): static {
        return $this->state(fn (array $attributes) => [
            'device' => 'Tablet',
            'user_agent' => $this->faker->safariUserAgent(),
        ]);
    }

    /**
     * Create a recent log (within last 24 hours)
     */
    public function recent(): static {
        return $this->state(fn (array $attributes) => [
            'login_time' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'logout_time' => $this->faker->optional(0.7)->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Create an old log (more than 30 days ago)
     */
    public function old(): static {
        return $this->state(fn (array $attributes) => [
            'login_time' => $this->faker->dateTimeBetween('-1 year', '-30 days'),
            'logout_time' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', '-30 days'),
        ]);
    }
}
