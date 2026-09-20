<?php

declare(strict_types=1);

use Laravel\Chisel\Script;

/* @chisel-audit-trails */

test('audit trails is registered in chisel application_features', function (): void {
    /** @var Script $script */
    $script = require base_path('chisel.php');

    $questions = $script->questions();
    $appFeatures = collect($questions)->firstWhere('name', 'application_features');

    expect($appFeatures)->not->toBeNull()
        ->and($appFeatures->options)->toHaveKey('audit-trails')
        ->and($appFeatures->default)->toContain('audit-trails');
});

test('audit trails modified files contain valid chisel markers', function (): void {
    $filesToCheck = [
        base_path('app/Enums/Permission.php'),
        base_path('app/Actions/Users/CreateUserAction.php'),
        base_path('app/Actions/Users/UpdateUserAction.php'),
        base_path('app/Actions/Users/ActivateUserAction.php'),
        base_path('app/Actions/Users/DeactivateUserAction.php'),
        base_path('app/Actions/Users/DeleteUserAction.php'),
        base_path('app/Actions/Users/ChangeUserPasswordAction.php'),
        base_path('app/Actions/UpdateSettings.php'),
        base_path('routes/web.php'),
        base_path('resources/js/types/index.ts'),
        base_path('resources/js/components/app-sidebar.tsx'),
        base_path('tests/Feature/InstallFeaturesCommandTest.php'),
        base_path('tests/Unit/Enums/PermissionTest.php'),
    ];

    foreach ($filesToCheck as $filePath) {
        $content = file_get_contents($filePath);
        expect($content)->toContain('chisel-audit-trails')
            ->and($content)->toContain('end-chisel-audit-trails');
    }
});

/* @end-chisel-audit-trails */
