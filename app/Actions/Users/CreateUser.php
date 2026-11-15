<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Data\AuditTrails\UserAuditData;
/* @end-chisel-audit-trails */
use App\Data\Users\CreateUserData;
/* @chisel-audit-trails */
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

final readonly class CreateUser
{
    public function __construct(
        private UpdateUserRoles $updateUserRoles = new UpdateUserRoles,
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        /* @end-chisel-audit-trails */
    ) {}

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
                $this->updateUserRoles->handle($user, $data->roles/* @chisel-audit-trails */, recordAuditTrail: false/* @end-chisel-audit-trails */);
            }

            event(new Registered($user));

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserCreated,
                auditable: $user,
                newValues: UserAuditData::fromModel($user, $data->roles)->toArray(),
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
