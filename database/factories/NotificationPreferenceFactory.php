<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
final class NotificationPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_type' => NotificationType::System,
            'database_enabled' => true,
        ];
    }

    public function disabled(): self
    {
        return $this->state(fn (array $attributes): array => [
            'database_enabled' => false,
        ]);
    }

    public function forType(NotificationType $type): self
    {
        return $this->state(fn (array $attributes): array => [
            'notification_type' => $type,
        ]);
    }
}
