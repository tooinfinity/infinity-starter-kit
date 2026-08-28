<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            /* @chisel-email-verification */
            'email_verified_at' => now(),
            /* @end-chisel-email-verification */
            'password' => 'password',
            'remember_token' => Str::random(10),
            /* @chisel-two-factor-authentication */
            'two_factor_secret' => Str::random(10),
            'two_factor_recovery_codes' => Str::random(10),
            'two_factor_confirmed_at' => now(),
            /* @end-chisel-two-factor-authentication */
        ];
    }

    /* @chisel-email-verification */
    public function unverified(): self
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
    /* @end-chisel-email-verification */

    /* @chisel-two-factor-authentication */
    public function withoutTwoFactor(): self
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
    /* @end-chisel-two-factor-authentication */
}
