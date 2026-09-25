<?php

declare(strict_types=1);

use App\Enums\ReportCategory;

test('report category enum has valid values', function (): void {
    expect(ReportCategory::values())->toBe([
        'users',
        'security',
    ]);
});

test('report category labels are localized', function (): void {
    expect(ReportCategory::Users->label())->toBe(__('reports.categories.users'))
        ->and(ReportCategory::Security->label())->toBe(__('reports.categories.security'));
});
