<?php

declare(strict_types=1);

namespace App\Actions;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
/* @end-chisel-audit-trails */
/* @chisel-audit-trails */
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
/* @chisel-user-management */
use App\Enums\SuperAdminRestrictionOperation;
/* @end-chisel-user-management */
use App\Models\User;
/* @chisel-user-management */
use App\Rules\NotLastSuperAdmin;
/* @end-chisel-user-management */
use Illuminate\Support\Facades\DB;
/* @chisel-roles-permissions */
use Spatie\Permission\Traits\HasRoles;

/* @end-chisel-roles-permissions */

final readonly class DeleteUser
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /* @chisel-user-management */
            NotLastSuperAdmin::check($user, SuperAdminRestrictionOperation::Delete);
            /* @end-chisel-user-management */

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserDeleted,
                auditable: $user,
                oldValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    /* @chisel-roles-permissions */
                    'roles' => trait_exists(HasRoles::class) ? $user->getRoleNames()->toArray() : [],
                    /* @end-chisel-roles-permissions */
                ],
                user: $user,
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            /* @chisel-notifications */
            $user->notifications()->delete();
            /* @end-chisel-notifications */

            $user->delete();
        });
    }
}
