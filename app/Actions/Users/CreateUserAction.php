<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Data\Users\CreateUserData;
/* @end-chisel-audit-trails */
use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserAction
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    public function handle(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'is_active' => $data->isActive,
            ]);

            if ($data->roles !== []) {
                $user->syncRoles($data->roles);
            }

            event(new Registered($user));

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserCreated,
                auditable: $user,
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'roles' => $data->roles,
                ],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
