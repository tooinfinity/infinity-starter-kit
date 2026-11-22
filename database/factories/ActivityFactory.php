<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
final class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => 'default',
            'description' => 'User created',
            'event' => 'created',
            'causer_type' => User::class,
            'causer_id' => User::factory(),
            'subject_type' => User::class,
            'subject_id' => (string) fake()->uuid(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'attribute_changes' => [
                'old' => null,
                'attributes' => [
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                ],
            ],
            'properties' => [
                'actor' => [
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                ],
                'request' => [
                    'url' => 'http://localhost/users',
                ],
                'tags' => ['users'],
            ],
            'created_at' => now(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(function (array $attributes) use ($user): array {
            $properties = is_array($attributes['properties'] ?? null) ? $attributes['properties'] : [];
            $properties['actor'] = [
                'name' => $user->name,
                'email' => $user->email,
            ];

            return [
                'causer_type' => $user->getMorphClass(),
                'causer_id' => $user->id,
                'properties' => $properties,
            ];
        });
    }

    public function withActor(?string $name, ?string $email): self
    {
        return $this->state(function (array $attributes) use ($name, $email): array {
            $properties = is_array($attributes['properties'] ?? null) ? $attributes['properties'] : [];
            $properties['actor'] = [
                'name' => $name,
                'email' => $email,
            ];

            return ['properties' => $properties];
        });
    }

    public function anonymous(): self
    {
        return $this->state(function (array $attributes): array {
            $properties = is_array($attributes['properties'] ?? null) ? $attributes['properties'] : [];
            $properties['actor'] = [
                'name' => null,
                'email' => null,
            ];

            return [
                'causer_type' => null,
                'causer_id' => null,
                'properties' => $properties,
            ];
        });
    }
}
