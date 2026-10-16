<?php

declare(strict_types=1);

use App\Enums\SuperAdminRestrictionOperation;

test('super admin restriction operation enum has expected cases and values', function (): void {
    expect(SuperAdminRestrictionOperation::Delete->value)->toBe('delete')
        ->and(SuperAdminRestrictionOperation::Deactivate->value)->toBe('deactivate')
        ->and(SuperAdminRestrictionOperation::RemoveRole->value)->toBe('remove_role');
});

test('super admin restriction operation cases are string-backed', function (): void {
    foreach (SuperAdminRestrictionOperation::cases() as $case) {
        expect($case->value)->toBeString();
    }
});
