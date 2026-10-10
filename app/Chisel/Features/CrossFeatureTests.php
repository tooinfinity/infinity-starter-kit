<?php

declare(strict_types=1);

namespace App\Chisel\Features;

/**
 * Declares test files and cross-cutting artifacts that span multiple optional features and must be pruned
 * if any of their required features are omitted from the project.
 */
final class CrossFeatureTests
{
    /**
     * @return list<array{features: list<string>, files: list<string>}>
     */
    public static function all(): array
    {
        return [
            [
                'features' => ['audit-trails', 'settings'],
                'files' => ['tests/Feature/AuditTrails/SettingsAuditingTest.php'],
            ],
            [
                'features' => ['audit-trails', 'user-management'],
                'files' => ['tests/Feature/AuditTrails/UserAuditingTest.php'],
            ],
            [
                'features' => ['notifications', 'localization'],
                'files' => ['tests/Feature/Notifications/NotificationLocalizationTest.php'],
            ],
            [
                'features' => ['reporting', 'localization'],
                'files' => ['tests/Feature/Reporting/ReportingLocalizationTest.php'],
            ],
        ];
    }
}
