<?php

declare(strict_types=1);

namespace App\Listeners\AuditTrails;

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Features;

final readonly class AuditAuthenticationEvents
{
    public function __construct(
        private RecordAuditTrail $recordAuditTrail = new RecordAuditTrail,
    ) {}

    public function onLogin(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthLogin,
            auditable: $user,
            newValues: ['guard' => $event->guard],
            user: $user,
            tags: ['auth', 'login'],
        );
    }

    public function onFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        if (! $user instanceof User && isset($event->credentials['email']) && is_string($event->credentials['email'])) {
            $user = User::query()->where('email', mb_strtolower(mb_trim($event->credentials['email'])))->first();
        }

        $newValues = ['guard' => $event->guard];

        if (app()->bound('request')) {
            $request = request();
            $email = $request->string('email')->trim()->lower()->transliterate()->value();

            if ($email !== '') {
                $throttleKey = $email.'|'.$request->ip();
                $newValues['throttled'] = RateLimiter::tooManyAttempts($throttleKey, 5);
                $newValues['attempts'] = RateLimiter::attempts($throttleKey);
            }
        }

        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthLoginFailed,
            auditable: $user,
            newValues: $newValues,
            user: $user,
            tags: ['auth', 'login'],
        );
    }

    public function onLockout(Lockout $event): void
    {
        $email = $event->request->string('email')->trim()->lower()->value();
        $user = $email !== '' ? User::query()->where('email', $email)->first() : null;

        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthLockout,
            auditable: $user,
            newValues: ['throttled' => true],
            user: $user,
            tags: ['auth', 'lockout'],
        );
    }

    public function onLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthLogout,
            auditable: $user,
            newValues: ['guard' => $event->guard],
            user: $user,
            tags: ['auth', 'logout'],
        );
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthPasswordReset,
            auditable: $user,
            user: $user,
            tags: ['auth', 'password'],
        );
    }

    public function onTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        if (class_exists(Features::class) && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')) {
            return;
        }

        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthTwoFactorEnabled,
            auditable: $event->user,
            user: $event->user,
            tags: ['auth', 'two-factor'],
        );
    }

    public function onTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthTwoFactorEnabled,
            auditable: $event->user,
            user: $event->user,
            tags: ['auth', 'two-factor'],
        );
    }

    public function onTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthTwoFactorDisabled,
            auditable: $event->user,
            user: $event->user,
            tags: ['auth', 'two-factor'],
        );
    }

    public function onRecoveryCodeReplaced(RecoveryCodeReplaced $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthRecoveryCodesUsed,
            auditable: $user,
            user: $user,
            tags: ['auth', 'two-factor'],
        );
    }

    public function onTwoFactorAuthenticationFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->recordAuditTrail->handle(
            event: AuditEvent::AuthLoginFailed,
            auditable: $event->user,
            newValues: ['guard' => 'web', 'two_factor' => true],
            user: $event->user,
            tags: ['auth', 'login', 'two-factor'],
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        $events = [
            Login::class => 'onLogin',
            Failed::class => 'onFailed',
            Lockout::class => 'onLockout',
            Logout::class => 'onLogout',
            PasswordReset::class => 'onPasswordReset',
        ];

        if (class_exists(TwoFactorAuthenticationEnabled::class)) {
            $events[TwoFactorAuthenticationEnabled::class] = 'onTwoFactorEnabled';
        }

        if (class_exists(TwoFactorAuthenticationConfirmed::class)) {
            $events[TwoFactorAuthenticationConfirmed::class] = 'onTwoFactorConfirmed';
        }

        if (class_exists(TwoFactorAuthenticationDisabled::class)) {
            $events[TwoFactorAuthenticationDisabled::class] = 'onTwoFactorDisabled';
        }

        if (class_exists(RecoveryCodeReplaced::class)) {
            $events[RecoveryCodeReplaced::class] = 'onRecoveryCodeReplaced';
        }

        if (class_exists(TwoFactorAuthenticationFailed::class)) {
            $events[TwoFactorAuthenticationFailed::class] = 'onTwoFactorAuthenticationFailed';
        }

        return $events;
    }
}
