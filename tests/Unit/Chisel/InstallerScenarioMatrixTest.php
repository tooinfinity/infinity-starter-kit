<?php

declare(strict_types=1);

use App\Chisel\FeatureRegistry;
use App\Chisel\Installer\DependencyValidator;
use App\Chisel\Installer\InstallerScenario;
use App\Chisel\Installer\InstallerState;

describe('Installer Scenario Matrix Integrity', function (): void {
    it('covers enabled and disabled states for every registered optional module', function (): void {
        $allScenarios = InstallerScenario::all();
        $optionalModules = FeatureRegistry::optionalModules();

        expect($optionalModules)->not->toBeEmpty();

        foreach ($optionalModules as $key => $feature) {
            $hasEnabled = false;
            $hasDisabled = false;

            foreach ($allScenarios as $scenario) {
                if ($scenario->hasFeature($key)) {
                    $hasEnabled = true;
                } else {
                    $hasDisabled = true;
                }

                if ($hasEnabled && $hasDisabled) {
                    break;
                }
            }

            expect($hasEnabled)->toBeTrue("Optional module [{$key}] must have at least one scenario where it is enabled.")
                ->and($hasDisabled)->toBeTrue("Optional module [{$key}] must have at least one scenario where it is disabled.");
        }
    });

    it('covers enabled and disabled states for every registered authentication feature', function (): void {
        $allScenarios = InstallerScenario::all();
        $authFeatures = FeatureRegistry::authFeatures();

        expect($authFeatures)->not->toBeEmpty();

        foreach ($authFeatures as $key => $feature) {
            $hasEnabled = false;
            $hasDisabled = false;

            foreach ($allScenarios as $scenario) {
                if ($scenario->hasFeature($key)) {
                    $hasEnabled = true;
                } else {
                    $hasDisabled = true;
                }

                if ($hasEnabled && $hasDisabled) {
                    break;
                }
            }

            expect($hasEnabled)->toBeTrue("Auth feature [{$key}] must have at least one scenario where it is enabled.")
                ->and($hasDisabled)->toBeTrue("Auth feature [{$key}] must have at least one scenario where it is disabled.");
        }
    });

    it('declares and covers all installer lifecycle phases', function (): void {
        expect(InstallerState::PHASES)->toBe([
            'preflight',
            'prune',
            'post_chisel',
            'migrate',
            'post_migration',
            'configure',
            'post_scaffold',
            'cleanup',
        ]);

        $failureScenarios = InstallerScenario::byCategory('failure');
        expect($failureScenarios)->not->toBeEmpty();

        $testedStages = [];
        foreach ($failureScenarios as $scenario) {
            if (isset($scenario->env['CHISEL_TEST_FAIL_STAGE'])) {
                $testedStages[] = $scenario->env['CHISEL_TEST_FAIL_STAGE'];
            }
        }

        expect($testedStages)->toContain('preflight')
            ->and($testedStages)->toContain('post_chisel');
    });

    it('validates dependency constraints for all features with prerequisites', function (): void {
        foreach (FeatureRegistry::all() as $key => $feature) {
            if ($feature->dependencies === []) {
                continue;
            }

            // Selecting the feature without its required dependencies must throw RuntimeException
            expect(fn () => DependencyValidator::validate([
                'auth_features' => [],
                'optional_modules' => [$key],
            ]))->toThrow(
                RuntimeException::class,
                sprintf('The "%s" feature requires the following feature(s):', $key),
            );
        }
    });

    it('ensures no overlapping expectations between present and absent elements across all scenarios', function (): void {
        foreach (InstallerScenario::all() as $id => $scenario) {
            $deps = $scenario->expectedDependencies();
            $absentDeps = $scenario->expectedAbsentDependencies();
            $depOverlap = array_intersect($deps, $absentDeps);
            expect($depOverlap)->toBeEmpty("Scenario [{$id}] has overlapping expected and absent dependencies: ".implode(', ', $depOverlap));

            $retainedPaths = $scenario->expectedRetainedPaths();
            $removedPaths = $scenario->expectedRemovedPaths();
            $pathOverlap = array_intersect($retainedPaths, $removedPaths);
            expect($pathOverlap)->toBeEmpty("Scenario [{$id}] has overlapping retained and removed paths: ".implode(', ', $pathOverlap));

            $presentTables = $scenario->expectedPresentTables();
            $absentTables = $scenario->expectedAbsentTables();
            $tableOverlap = array_intersect($presentTables, $absentTables);
            expect($tableOverlap)->toBeEmpty("Scenario [{$id}] has overlapping present and absent tables: ".implode(', ', $tableOverlap));

            $presentCols = $scenario->expectedPresentColumns();
            $absentCols = $scenario->expectedAbsentColumns();
            foreach ($presentCols as $table => $cols) {
                if (isset($absentCols[$table])) {
                    $colOverlap = array_intersect($cols, $absentCols[$table]);
                    expect($colOverlap)->toBeEmpty("Scenario [{$id}] has overlapping present and absent columns on [{$table}]: ".implode(', ', $colOverlap));
                }
            }
        }
    });

    it('synchronizes all registered scenarios with the documented scenario matrix', function (): void {
        $matrixPath = base_path('docs/installer-matrix.md');
        expect(file_exists($matrixPath))->toBeTrue('docs/installer-matrix.md must exist.');

        $matrixContent = (string) file_get_contents($matrixPath);

        foreach (InstallerScenario::all() as $id => $scenario) {
            expect(str_contains($matrixContent, "`{$id}`"))
                ->toBeTrue("Scenario [{$id}] ({$scenario->name}) is missing from docs/installer-matrix.md. Keep the matrix documentation up to date.");
        }
    });

    it('provides representative scenarios dataset for E2E and smoke suites', function (): void {
        $representative = InstallerScenario::representative();

        expect($representative)->toHaveKeys([
            'minimal',
            'default',
            'full',
            'dependency-heavy',
            'admin-no-credentials',
            'db-early-migration-fresh',
            'env-no-node',
            'failure-post-chisel-resume',
        ]);

        foreach ($representative as $scenario) {
            expect($scenario->id)->toBeString()
                ->and($scenario->name)->toBeString()
                ->and($scenario->answers())->toHaveKeys(['auth_features', 'optional_modules']);
        }
    });
});
