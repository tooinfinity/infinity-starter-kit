<?php

declare(strict_types=1);

use App\Actions\Users\SnapshotUserAuditDataAction;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

it('snapshots user audit data using assigned roles', function (): void {
    RoleModel::findOrCreate('editor');

    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_active' => true,
    ]);
    $user->assignRole('editor');

    $action = resolve(SnapshotUserAuditDataAction::class);
    $snapshot = $action->handle($user);

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

    $action = resolve(SnapshotUserAuditDataAction::class);
    $snapshot = $action->handle($user, ['admin', 'manager']);

    expect($snapshot)->toBe([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'is_active' => false,
        'roles' => ['admin', 'manager'],
    ]);
});
