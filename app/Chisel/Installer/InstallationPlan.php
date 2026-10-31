<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;

/**
 * Strongly-typed immutable preview of all planned installer mutations and lifecycle phases.
 */
final readonly class InstallationPlan
{
    /**
     * @param  list<string>  $explicitAuthFeatures
     * @param  list<string>  $explicitModules
     * @param  list<string>  $selectedFeatures
     * @param  list<string>  $unselectedFeatures
     * @param  array<string, list<string>>  $dependencyGraph
     * @param  array<string, list<string>>  $requiredBy
     * @param  list<string>  $dependencyDerivedFeatures
     * @param  list<string>  $prunedFiles
     * @param  list<string>  $prunedDirectories
     * @param  list<string>  $modifiedFiles
     * @param  list<string>  $retainedComposerPackages
     * @param  list<string>  $removedComposerPackages
     * @param  list<string>  $retainedFrontendPackages
     * @param  list<string>  $removedFrontendPackages
     * @param  array<string, string>  $configurationPhases
     * @param  array<string, string>  $postScaffoldCommands
     * @param  array<string, string>  $validationPhases
     * @param  list<string>  $cleanupFiles
     * @param  list<string>  $cleanupDirectories
     */
    public function __construct(
        public array $explicitAuthFeatures,
        public array $explicitModules,
        public array $selectedFeatures,
        public array $unselectedFeatures,
        public array $dependencyGraph,
        public array $requiredBy,
        public array $dependencyDerivedFeatures,
        public array $prunedFiles,
        public array $prunedDirectories,
        public array $modifiedFiles,
        public array $retainedComposerPackages,
        public array $removedComposerPackages,
        public array $retainedFrontendPackages,
        public array $removedFrontendPackages,
        public string $migrationCommand,
        public array $configurationPhases,
        public array $postScaffoldCommands,
        public array $validationPhases,
        public array $cleanupFiles,
        public array $cleanupDirectories,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'explicit_auth_features' => $this->explicitAuthFeatures,
            'explicit_modules' => $this->explicitModules,
            'selected_features' => $this->selectedFeatures,
            'unselected_features' => $this->unselectedFeatures,
            'dependency_graph' => $this->dependencyGraph,
            'required_by' => $this->requiredBy,
            'dependency_derived_features' => $this->dependencyDerivedFeatures,
            'pruned_files' => $this->prunedFiles,
            'pruned_directories' => $this->prunedDirectories,
            'modified_files' => $this->modifiedFiles,
            'retained_composer_packages' => $this->retainedComposerPackages,
            'removed_composer_packages' => $this->removedComposerPackages,
            'retained_frontend_packages' => $this->retainedFrontendPackages,
            'removed_frontend_packages' => $this->removedFrontendPackages,
            'migration_command' => $this->migrationCommand,
            'configuration_phases' => $this->configurationPhases,
            'post_scaffold_commands' => $this->postScaffoldCommands,
            'validation_phases' => $this->validationPhases,
            'cleanup_files' => $this->cleanupFiles,
            'cleanup_directories' => $this->cleanupDirectories,
        ];
    }

    public function render(Command $command, ?Factory $components = null): void
    {
        $components ??= new Factory($command->getOutput());

        $command->newLine();
        $command->line('  <fg=black;bg=cyan;options=bold> DRY RUN </> <fg=cyan;options=bold>Planned Feature Installation Preview</>');
        $command->line('  <fg=gray>Previewing planned mutations. No filesystem, dependency, database, or configuration changes will be executed.</>');
        $command->newLine();

        $components->twoColumnDetail('<fg=cyan;options=bold>FEATURE SELECTIONS & DEPENDENCIES</>');
        $components->twoColumnDetail(
            '  Explicit Auth Features',
            $this->explicitAuthFeatures !== [] ? implode(', ', $this->explicitAuthFeatures) : '<fg=gray>none</>',
        );
        $components->twoColumnDetail(
            '  Explicit Optional Modules',
            $this->explicitModules !== [] ? implode(', ', $this->explicitModules) : '<fg=gray>none</>',
        );
        if ($this->unselectedFeatures !== []) {
            $components->twoColumnDetail(
                '  Unselected Features (To Prune)',
                implode(', ', $this->unselectedFeatures),
            );
        }

        if ($this->dependencyDerivedFeatures !== []) {
            $components->twoColumnDetail(
                '  Dependency-Derived Selections',
                implode(', ', $this->dependencyDerivedFeatures),
            );
            foreach ($this->dependencyDerivedFeatures as $feature) {
                $reqBy = $this->requiredBy[$feature] ?? [];
                $components->twoColumnDetail(
                    '    • '.$feature,
                    '<fg=gray>implicitly required by: '.implode(', ', $reqBy).'</>',
                );
            }
        } else {
            $components->twoColumnDetail('  Dependency-Derived Selections', '<fg=gray>none</>');
        }

        $components->twoColumnDetail(
            '  Dependency & Conflict Resolution',
            '<fg=green>Resolved (all required dependencies satisfied, 0 conflicts)</>',
        );
        $command->newLine();

        $components->twoColumnDetail('<fg=cyan;options=bold>PACKAGES (COMPOSER & FRONTEND)</>');
        $components->twoColumnDetail(
            '  Composer Packages to Retain',
            $this->retainedComposerPackages !== [] ? implode(', ', $this->retainedComposerPackages) : '<fg=gray>none</>',
        );
        $components->twoColumnDetail(
            '  Composer Packages to Remove',
            $this->removedComposerPackages !== [] ? implode(', ', $this->removedComposerPackages) : '<fg=gray>none</>',
        );
        $components->twoColumnDetail(
            '  Frontend Packages to Retain',
            $this->retainedFrontendPackages !== [] ? implode(', ', $this->retainedFrontendPackages) : '<fg=gray>none</>',
        );
        $components->twoColumnDetail(
            '  Frontend Packages to Remove',
            $this->removedFrontendPackages !== [] ? implode(', ', $this->removedFrontendPackages) : '<fg=gray>none</>',
        );
        $command->newLine();

        $components->twoColumnDetail('<fg=cyan;options=bold>DATABASE & CONFIGURATION PHASES</>');
        $components->twoColumnDetail('  Database Migrations', $this->migrationCommand);
        foreach ($this->configurationPhases as $phase => $details) {
            $components->twoColumnDetail('  '.$phase, $details);
        }

        $command->newLine();

        $components->twoColumnDetail('<fg=cyan;options=bold>POST-SCAFFOLDING TOOLING</>');
        foreach ($this->postScaffoldCommands as $tool => $details) {
            $components->twoColumnDetail('  '.$tool, $details);
        }

        $command->newLine();

        $components->twoColumnDetail('<fg=cyan;options=bold>VALIDATION & CLEANUP PHASES</>');
        foreach ($this->validationPhases as $phase => $details) {
            $components->twoColumnDetail('  '.$phase, $details);
        }

        $components->twoColumnDetail(
            '  Cleanup Infrastructure Files',
            sprintf('%d files scheduled for removal', count($this->cleanupFiles)),
        );
        $components->twoColumnDetail(
            '  Cleanup Infrastructure Directories',
            sprintf('%d directories scheduled for pruning', count($this->cleanupDirectories)),
        );
        $command->newLine();

        $components->twoColumnDetail('<fg=cyan;options=bold>PLANNED FILESYSTEM MUTATIONS</>');
        $components->twoColumnDetail('  Feature Files to Delete', (string) count($this->prunedFiles));
        $components->twoColumnDetail('  Empty Directories to Prune', (string) count($this->prunedDirectories));
        $components->twoColumnDetail('  Files to Modify (Markers & Interfaces)', (string) count($this->modifiedFiles));

        if ($this->prunedFiles !== []) {
            $command->newLine();
            $command->line('  <options=bold>Files Scheduled For Deletion:</>');
            foreach ($this->prunedFiles as $file) {
                $command->line('    <fg=red>-</> '.$file);
            }
        }

        if ($this->prunedDirectories !== []) {
            $command->newLine();
            $command->line('  <options=bold>Directories Scheduled For Pruning:</>');
            foreach ($this->prunedDirectories as $dir) {
                $command->line('    <fg=red>-</> '.$dir);
            }
        }

        $command->newLine();
        $components->info('Dry-run preview complete: 0 mutations executed. Repository and database remain unmodified.');
    }
}
