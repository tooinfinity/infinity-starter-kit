<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chisel\FeatureMatrixGenerator;
use App\Chisel\ManifestExampleGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('chisel:docs
        {--check : Check if generated documentation is up to date without modifying files}
        {--path=README.md : Path to the documentation file to update or verify}')]
#[Description('Generate or verify optional-feature matrix and manifest example documentation from authoritative definitions')]
final class GenerateFeatureDocsCommand extends Command
{
    public function handle(): int
    {
        $pathOption = (string) ($this->option('path') ?? 'README.md');
        $fullPath = str_starts_with($pathOption, '/') ? $pathOption : base_path($pathOption);

        if (! file_exists($fullPath)) {
            $this->components->error(sprintf('Documentation file [%s] does not exist.', $pathOption));

            return self::FAILURE;
        }

        $content = (string) file_get_contents($fullPath);
        $table = FeatureMatrixGenerator::generateOptionalModulesTable();
        $hasManifestMarkers = ManifestExampleGenerator::hasMarkers($content);

        if ($this->option('check')) {
            if (! FeatureMatrixGenerator::isContentFresh($content, $table)) {
                $this->components->error(sprintf(
                    'Generated feature matrix in [%s] is stale or missing. Run `php artisan chisel:docs` to regenerate.',
                    $pathOption,
                ));

                return self::FAILURE;
            }

            if ($hasManifestMarkers && ! ManifestExampleGenerator::isContentFresh($content)) {
                $this->components->error(sprintf(
                    'Generated manifest example in [%s] is stale or missing. Run `php artisan chisel:docs` to regenerate.',
                    $pathOption,
                ));

                return self::FAILURE;
            }

            $this->components->info(sprintf('Generated documentation in [%s] is up to date.', $pathOption));

            return self::SUCCESS;
        }

        try {
            $updated = FeatureMatrixGenerator::updateContent($content, $table);
            if ($hasManifestMarkers) {
                $updated = ManifestExampleGenerator::updateContent($updated);
            }

            file_put_contents($fullPath, $updated);
            $this->components->info(sprintf('Generated documentation in [%s] updated successfully.', $pathOption));

            return self::SUCCESS;
        } catch (RuntimeException $runtimeException) {
            $this->components->error($runtimeException->getMessage());

            return self::FAILURE;
        }
    }
}
