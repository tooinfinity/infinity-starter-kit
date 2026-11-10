<?php

declare(strict_types=1);

use App\Data\AuditTrails\UserAuditData;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

it('creates user audit data from model using assigned roles', function (): void {
    RoleModel::findOrCreate('editor');

    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
    $user->assignRole('editor');

    $data = UserAuditData::fromModel($user);

    $expected = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ];

    if (array_key_exists('is_active', $user->getAttributes())) {
        $expected['is_active'] = (bool) $user->is_active;
    }

    $expected['roles'] = ['editor'];

    expect($data->toArray())->toBe($expected)
        ->and($data->name)->toBe('John Doe')
        ->and($data->email)->toBe('john@example.com')
        ->and($data->roles)->toBe(['editor']);
});

it('creates user audit data from model with explicit roles', function (): void {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $data = UserAuditData::fromModel($user, ['admin', 'manager']);

    $expected = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ];

    if (array_key_exists('is_active', $user->getAttributes())) {
        $expected['is_active'] = (bool) $user->is_active;
    }

    $expected['roles'] = ['admin', 'manager'];

    expect($data->toArray())->toBe($expected);
});

it('omits is_active when attribute is not present on user', function (): void {
    $user = new User([
        'name' => 'No Status User',
        'email' => 'nostatus@example.com',
    ]);

    $data = UserAuditData::fromModel($user);

    expect($data->toArray())->toBe([
        'name' => 'No Status User',
        'email' => 'nostatus@example.com',
        'roles' => [],
    ]);
});

it('snapshots user audit data with inactive status when attribute is present', function (): void {
    $user = new User([
        'name' => 'Inactive User',
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);

    $data = UserAuditData::fromModel($user);

    expect($data->toArray())->toBe([
        'name' => 'Inactive User',
        'email' => 'inactive@example.com',
        'is_active' => false,
        'roles' => [],
    ]);
});
