<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Models\User;
/* @chisel-notifications */
use App\Notifications\UserActivated;
/* @end-chisel-notifications */
use Illuminate\Support\Facades\DB;

final readonly class ActivateUserAction
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        /* @end-chisel-audit-trails */
        private EnsureActorMayManageTargetAction $ensureActorMayManageTarget = new EnsureActorMayManageTargetAction,
    ) {}

    public function handle(User $user, ?User $currentUser = null): void
    {
        if ($currentUser instanceof User) {
            $this->ensureActorMayManageTarget->ensure($currentUser, $user);
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['is_active' => true])->save();

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserActivated,
                auditable: $user,
                oldValues: ['is_active' => false],
                newValues: ['is_active' => true],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            /* @chisel-notifications */
            $user->notify(new UserActivated);
            /* @end-chisel-notifications */
        });
    }
}
