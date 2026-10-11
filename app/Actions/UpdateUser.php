<?php

declare(strict_types=1);

namespace App\Actions;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Models\User;

final readonly class UpdateUser
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): void
    {
        /* @chisel-audit-trails */
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
        ];
        /* @end-chisel-audit-trails */

        /* @chisel-email-verification */
        $emailChanged = isset($attributes['email']) && $user->email !== $attributes['email'];
        /* @end-chisel-email-verification */

        $user->update([
            ...$attributes,
            /* @chisel-email-verification */
            ...($emailChanged ? ['email_verified_at' => null] : []),
            /* @end-chisel-email-verification */
        ]);

        /* @chisel-email-verification */
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        /* @end-chisel-email-verification */

        /* @chisel-audit-trails */
        $this->auditTrail->handle(
            event: AuditEvent::UserUpdated,
            auditable: $user,
            oldValues: $oldValues,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
            ],
            tags: ['users', 'profile'],
        );
        /* @end-chisel-audit-trails */
    }
}
