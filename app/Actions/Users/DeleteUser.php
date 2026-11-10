<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Actions\AuditTrails\UpdateUserAuditTrails;
use App\Data\AuditTrails\UserAuditData;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteUser
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        private UpdateUserAuditTrails $updateUserAuditTrails = new UpdateUserAuditTrails,
        /* @end-chisel-audit-trails */
    ) {}

    public function handle(User $user, ?User $currentUser = null): void
    {
        if ($currentUser instanceof User && $user->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot delete your own account.'),
            ]);
        }

        DB::transaction(function () use ($user): void {
            NotLastSuperAdmin::check($user, SuperAdminRestrictionOperation::Delete);

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserDeleted,
                auditable: $user,
                oldValues: UserAuditData::fromModel($user)->toArray(),
                tags: ['users'],
            );

            $this->updateUserAuditTrails->handle($user);
            /* @end-chisel-audit-trails */

            /* @chisel-notifications */
            $user->notifications()->delete();
            /* @end-chisel-notifications */

            $user->delete();
        });
    }
}
