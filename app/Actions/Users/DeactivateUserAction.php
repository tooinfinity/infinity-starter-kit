<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
/* @chisel-notifications */
use App\Notifications\UserDeactivated;
/* @end-chisel-notifications */
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeactivateUserAction
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        /* @end-chisel-audit-trails */
    ) {}

    public function handle(User $user, ?User $currentUser = null): void
    {
        if ($currentUser instanceof User && $user->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot deactivate your own account.'),
            ]);
        }

        DB::transaction(function () use ($user): void {
            NotLastSuperAdmin::check($user, SuperAdminRestrictionOperation::Deactivate);

            $user->forceFill(['is_active' => false])->save();

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserDeactivated,
                auditable: $user,
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            /* @chisel-notifications */
            $user->notify(new UserDeactivated);
            /* @end-chisel-notifications */
        });
    }
}
