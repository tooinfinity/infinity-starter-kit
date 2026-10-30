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
use App\Notifications\UserActivated;
use App\Notifications\UserDeactivated;
/* @end-chisel-notifications */
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateUserStatus
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        /* @end-chisel-audit-trails */
    ) {}

    public function handle(User $user, bool $isActive, ?User $currentUser = null): void
    {
        if (! $isActive && $currentUser instanceof User && $user->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot deactivate your own account.'),
            ]);
        }

        DB::transaction(function () use ($user, $isActive): void {
            if (! $isActive) {
                NotLastSuperAdmin::check($user, SuperAdminRestrictionOperation::Deactivate);
            }

            $user->forceFill(['is_active' => $isActive])->save();

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: $isActive ? AuditEvent::UserActivated : AuditEvent::UserDeactivated,
                auditable: $user,
                oldValues: ['is_active' => ! $isActive],
                newValues: ['is_active' => $isActive],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            /* @chisel-notifications */
            $user->notify($isActive ? new UserActivated : new UserDeactivated);
            /* @end-chisel-notifications */
        });
    }
}
