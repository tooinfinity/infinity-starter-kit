<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Enums\Role;
/* @end-chisel-audit-trails */
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

final readonly class DeleteUserAction
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
                'user' => __('You cannot delete your own account.'),
            ]);
        }

        if (trait_exists(HasRoles::class) && $user->hasRole(Role::SuperAdmin->value)) {
            $superAdminCount = User::query()->role(Role::SuperAdmin->value)->count();

            if ($superAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => __('Cannot delete the last Super Admin.'),
                ]);
            }
        }

        DB::transaction(function () use ($user): void {
            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserDeleted,
                auditable: $user,
                oldValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'roles' => trait_exists(HasRoles::class) ? $user->getRoleNames()->toArray() : [],
                ],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            $user->delete();
        });
    }
}
