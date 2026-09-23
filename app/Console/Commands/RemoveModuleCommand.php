<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Module;
use App\Exceptions\DependentModuleException;
use App\Support\Chisel\ModuleRemover;
use App\Support\Chisel\ModuleResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Chisel\Chisel;

#[Signature('module:remove {module : The identifier of the module to remove} {--force : Bypass confirmation}')]
#[Description('Safely remove an optional module from the application using Chisel')]
final class RemoveModuleCommand extends Command
{
    public function handle(): int
    {
        $rawModule = (string) $this->argument('module');

        $module = $rawModule === 'roles-permissions'
            ? Module::Authorization
            : Module::tryFrom($rawModule);

        if ($module === null) {
            $this->components->error("Invalid module [{$rawModule}]. Available modules: ".implode(', ', Module::defaultValues()));

            return self::FAILURE;
        }

        $installedModules = $this->detectInstalledModules();

        if (! in_array($module, $installedModules, true)) {
            $this->components->info("Module [{$module->label()}] is not installed.");

            return self::SUCCESS;
        }

        try {
            ModuleResolver::validateRemoval($module, $installedModules);
        } catch (DependentModuleException $dependentModuleException) {
            $this->components->error($dependentModuleException->getMessage());

            return self::FAILURE;
        }

        $this->components->warn('Removing a module removes its source code and configuration. It does not automatically destroy production database data.');

        if (! $this->option('force') && $this->input->isInteractive()) {
            $confirmed = $this->confirm("Are you sure you want to remove the [{$module->label()}] module?", false);

            if (! $confirmed) {
                $this->components->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $remainingModules = array_values(array_filter(
            $installedModules,
            fn (Module $m): bool => $m !== $module,
        ));

        $chisel = Chisel::in(base_path());
        ModuleRemover::remove($module, $chisel, base_path(), $remainingModules);

        if (file_exists(base_path('artisan'))) {
            $this->callSilent('wayfinder:generate', ['--with-form' => true, '--no-interaction' => true]);
        }

        $this->components->info("Module [{$module->label()}] successfully removed.");

        return self::SUCCESS;
    }

    /**
     * Detect which optional modules are currently installed in the workspace.
     *
     * @return list<Module>
     */
    private function detectInstalledModules(): array
    {
        $installed = [];

        foreach (Module::cases() as $case) {
            // A module is considered installed if its primary owned file exists
            $firstOwned = $case->ownedFiles()[0] ?? null;

            if ($firstOwned !== null && file_exists(base_path($firstOwned))) {
                $installed[] = $case;
            }
        }

        return $installed;
    }
}
