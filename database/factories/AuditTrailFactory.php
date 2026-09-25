<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditTrail>
 */
final class AuditTrailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event' => AuditEvent::UserCreated,
            'auditable_type' => User::class,
            'auditable_id' => (string) fake()->uuid(),
            'old_values' => null,
            'new_values' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
            ],
            'url' => 'http://localhost/users',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'tags' => ['users'],
            'created_at' => now(),
        ];
    }

    public function forEvent(AuditEvent $event): self
    {
        return $this->state(fn (array $attributes): array => [
            'event' => $event,
        ]);
    }

    public function anonymous(): self
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }
}
