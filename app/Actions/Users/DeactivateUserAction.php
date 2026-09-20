<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Enums\Role;
use App\Models\User;
/* @chisel-notifications */
use App\Notifications\UserDeactivated;
/* @end-chisel-notifications */
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeactivateUserAction
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    public function handle(User $user, ?User $currentUser = null): void
    {
        if ($currentUser instanceof User && $user->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot deactivate your own account.'),
            ]);
        }

        if ($user->hasRole(Role::SuperAdmin->value)) {
            $superAdminCount = User::query()->role(Role::SuperAdmin->value)->count();

            if ($superAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => __('Cannot deactivate the last Super Admin.'),
                ]);
            }
        }

        DB::transaction(function () use ($user): void {
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
