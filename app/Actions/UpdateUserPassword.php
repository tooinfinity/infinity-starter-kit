<?php

declare(strict_types=1);

namespace App\Actions;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Models\User;
/* @chisel-notifications */
use App\Notifications\PasswordChanged;
/* @end-chisel-notifications */
use SensitiveParameter;

final readonly class UpdateUserPassword
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        $user->update([
            'password' => $password,
        ]);

        /* @chisel-notifications */
        $user->notify(new PasswordChanged);
        /* @end-chisel-notifications */

        /* @chisel-audit-trails */
        $this->auditTrail->handle(
            event: AuditEvent::UserPasswordChanged,
            auditable: $user,
            newValues: ['password' => '[REDACTED]'],
            tags: ['users', 'security'],
        );
        /* @end-chisel-audit-trails */
    }
}
