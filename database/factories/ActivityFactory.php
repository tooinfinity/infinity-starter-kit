<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditEvent;
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
            'description' => AuditEvent::UserCreated->label(),
            'event' => AuditEvent::UserCreated,
            'causer_type' => User::class,
            'causer_id' => User::factory(),
            'subject_type' => User::class,
            'subject_id' => (string) fake()->uuid(),
            'properties' => [
                'actor' => [
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                ],
                'request' => [
                    'url' => 'http://localhost/users',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ],
                'old' => null,
                'attributes' => [
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                ],
                'tags' => ['users'],
            ],
            'created_at' => now(),
        ];
    }

    public function forEvent(AuditEvent $event): self
    {
        return $this->state(fn (array $attributes): array => [
            'event' => $event,
            'description' => $event->label(),
        ]);
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
