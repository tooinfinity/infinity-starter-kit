<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\UserAuditData;
use Spatie\Permission\Models\Role as RoleModel;

it('snapshots user audit data using assigned roles', function (): void {
    RoleModel::findOrCreate('editor');

    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_active' => true,
    ]);
    $user->assignRole('editor');

    $service = resolve(UserAuditData::class);
    $snapshot = $service->snapshot($user);

    expect($snapshot)->toBe([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_active' => true,
        'roles' => ['editor'],
    ]);
});

it('snapshots user audit data with explicit roles', function (): void {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'is_active' => false,
    ]);

    $service = resolve(UserAuditData::class);
    $snapshot = $service->snapshot($user, ['admin', 'manager']);

    expect($snapshot)->toBe([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'is_active' => false,
        'roles' => ['admin', 'manager'],
    ]);
});
