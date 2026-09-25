<?php

declare(strict_types=1);

use App\Actions\Users\ActivateUserAction;
use App\Actions\Users\ChangeUserPasswordAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeactivateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Data\Users\CreateUserData;
use App\Data\Users\UpdateUserData;
use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;

test('CreateUserAction records user.created audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $action = resolve(CreateUserAction::class);
    $data = new CreateUserData(
        name: 'Jane Doe',
        email: 'jane@example.com',
        password: 'password123',
        isActive: true,
        roles: [],
    );

    $createdUser = $action->handle($data);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserCreated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($createdUser->id)
        ->and($audit->new_values['name'])->toBe('Jane Doe')
        ->and($audit->new_values['email'])->toBe('jane@example.com')
        ->and($audit->new_values['is_active'])->toBeTrue();
});

test('UpdateUserAction records user.updated audit trail with diff', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'orig@example.com',
        'is_active' => true,
    ]);

    $action = resolve(UpdateUserAction::class);
    $data = new UpdateUserData(
        name: 'Updated Name',
        email: 'updated@example.com',
        isActive: true,
        roles: [],
    );

    $action->handle($targetUser, $data, $actor);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserUpdated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->old_values['name'])->toBe('Original Name')
        ->and($audit->old_values['email'])->toBe('orig@example.com')
        ->and($audit->new_values['name'])->toBe('Updated Name')
        ->and($audit->new_values['email'])->toBe('updated@example.com');
});

test('ActivateUserAction records user.activated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->inactive()->create();

    $action = resolve(ActivateUserAction::class);
    $action->handle($targetUser);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserActivated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->old_values['is_active'])->toBeFalse()
        ->and($audit->new_values['is_active'])->toBeTrue();
});

test('DeactivateUserAction records user.deactivated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create(['is_active' => true]);

    $action = resolve(DeactivateUserAction::class);
    $action->handle($targetUser, $actor);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserDeactivated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->old_values['is_active'])->toBeTrue()
        ->and($audit->new_values['is_active'])->toBeFalse();
});

test('DeleteUserAction records user.deleted audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create([
        'name' => 'To Be Deleted',
        'email' => 'delete_me@example.com',
    ]);
    $targetId = $targetUser->id;

    $action = resolve(DeleteUserAction::class);
    $action->handle($targetUser, $actor);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserDeleted)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetId)
        ->and($audit->old_values['name'])->toBe('To Be Deleted')
        ->and($audit->old_values['email'])->toBe('delete_me@example.com');
});

test('ChangeUserPasswordAction records user.password_changed audit trail with redacted password', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create();

    $action = resolve(ChangeUserPasswordAction::class);
    $action->handle($targetUser, 'brand_new_secret_password');

    $audit = AuditTrail::query()->where('event', AuditEvent::UserPasswordChanged)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->new_values['password'])->toBe('[REDACTED]')
        ->and(json_encode($audit->new_values))->not->toContain('brand_new_secret_password');
});
