<?php

declare(strict_types=1);

namespace App\Models;

/* @chisel-localization */
use App\Enums\Locale;
/* @end-chisel-localization */
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
/* @chisel-roles-permissions */
use Spatie\Permission\Traits\HasRoles;

/* @end-chisel-roles-permissions */

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $email
 * @property-read CarbonInterface|null $email_verified_at
 * @property-read string $password
 * @property-read string|null $remember_token
 * @property-read string|null $two_factor_secret
 * @property-read string|null $two_factor_recovery_codes
 * @property-read CarbonInterface|null $two_factor_confirmed_at
 * @property-read bool $is_active
 * @property-read Locale|null $locale
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
final class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /* @chisel-roles-permissions */
    use HasRoles;

    /* @end-chisel-roles-permissions */
    use HasUuids;

    /* @chisel-notifications */
    use Notifiable;

    /* @end-chisel-notifications */
    /* @chisel-two-factor-authentication */
    use TwoFactorAuthenticatable;

    /* @end-chisel-two-factor-authentication */

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'email' => 'string',
            /* @chisel-email-verification */
            'email_verified_at' => 'datetime',
            /* @end-chisel-email-verification */
            'password' => 'hashed',
            'remember_token' => 'string',
            /* @chisel-two-factor-authentication */
            'two_factor_secret' => 'string',
            'two_factor_recovery_codes' => 'string',
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-two-factor-authentication */
            /* @chisel-user-management */
            'is_active' => 'boolean',
            /* @end-chisel-user-management */
            /* @chisel-localization */
            'locale' => Locale::class,
            /* @end-chisel-localization */
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /* @chisel-localization */
    public function preferredLocale(): ?string
    {
        return $this->locale?->value;
    }

    /* @end-chisel-localization */

    /* @chisel-notifications */
    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /* @end-chisel-notifications */
}
