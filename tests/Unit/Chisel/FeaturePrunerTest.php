<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\Installer\FeaturePruner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Laravel\Chisel\Chisel;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/feature_pruner_test_'.bin2hex(random_bytes(6));
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        $cleanup = function (string $dir) use (&$cleanup): void {
            $items = scandir($dir);
            if ($items === false) {
                return;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $dir.'/'.$item;
                if (is_dir($path)) {
                    $cleanup($path);
                } else {
                    File::delete($path);
                }
            }

            @rmdir($dir);
        };
        $cleanup($this->tempDir);
    }
});

test('feature pruner applySelected unwraps section markers for selected feature', function (): void {
    $file = $this->tempDir.'/test.php';
    $openTag = '/* @'.'chisel-test */';
    $closeTag = '/* @'.'end-chisel-test */';
    file_put_contents($file, "{$openTag}\nkept code;\n{$closeTag}\n");

    $feature = new FeatureDefinition(
        key: 'test',
        label: 'Test Feature',
        sectionMarker: 'test',
        markerFiles: ['test.php'],
    );

    $chisel = Chisel::in($this->tempDir);
    FeaturePruner::applySelected($chisel, $feature);

    $content = (string) file_get_contents($file);
    expect($content)->not->toContain('@'.'chisel-test')
        ->and($content)->toContain('kept code;');
});

test('feature pruner applySelected does nothing when marker files or section marker is empty', function (): void {
    $feature = new FeatureDefinition(
        key: 'test',
        label: 'Test Feature',
        markerFiles: [],
    );

    $chisel = Chisel::in($this->tempDir);
    expect(fn () => FeaturePruner::applySelected($chisel, $feature))->not->toThrow(Throwable::class);
});

test('feature pruner pruneUnselected removes sections, deletes exclusive files, removes packages, and prunes empty dirs', function (): void {
    Process::fake();

    $markerFile = $this->tempDir.'/marker.php';
    $openTag = '/* @'.'chisel-unselected */';
    $closeTag = '/* @'.'end-chisel-unselected */';
    file_put_contents($markerFile, "{$openTag}\npruned code;\n{$closeTag}\nouter code;\n");

    $exclusiveFile = $this->tempDir.'/exclusive.php';
    file_put_contents($exclusiveFile, "<?php // exclusive\n");

    $composerFile = $this->tempDir.'/composer.json';
    file_put_contents($composerFile, json_encode([
        'name' => 'test/app',
        'require' => ['vendor/dummy' => '^1.0'],
    ]));

    $packageFile = $this->tempDir.'/package.json';
    file_put_contents($packageFile, json_encode([
        'name' => 'test-app',
        'dependencies' => ['npm-dummy' => '^1.0'],
    ]));

    $emptyDir = $this->tempDir.'/app/EmptyDir';
    mkdir($emptyDir, 0777, true);

    $feature = new FeatureDefinition(
        key: 'unselected',
        label: 'Unselected',
        sectionMarker: 'unselected',
        markerFiles: ['marker.php'],
        exclusiveFiles: ['exclusive.php'],
        emptyDirectories: ['app/EmptyDir'],
        composerPackages: ['vendor/dummy'],
        frontendPackages: ['npm-dummy'],
    );

    $chisel = Chisel::in($this->tempDir);
    FeaturePruner::pruneUnselected($this->tempDir, $chisel, $feature);

    $markerContent = (string) file_get_contents($markerFile);
    expect($markerContent)->not->toContain('pruned code;')
        ->and($markerContent)->toContain('outer code;')
        ->and(file_exists($exclusiveFile))->toBeFalse()
        ->and(is_dir($emptyDir))->toBeFalse();

    $composerContent = json_decode((string) file_get_contents($composerFile), true);
    expect($composerContent['require'] ?? [])->not->toHaveKey('vendor/dummy');

    $packageContent = json_decode((string) file_get_contents($packageFile), true);
    expect($packageContent['dependencies'] ?? [])->not->toHaveKey('npm-dummy');
});

test('feature pruner register wires selected and unselected callbacks with beforePrune and afterPrune hooks', function (): void {
    $file = $this->tempDir.'/feature.php';
    $openTag = '/* @'.'chisel-alpha */';
    $closeTag = '/* @'.'end-chisel-alpha */';
    $betaOpen = '/* @'.'chisel-beta */';
    $betaClose = '/* @'.'end-chisel-beta */';
    file_put_contents($file, "{$openTag}\nalpha();\n{$closeTag}\n{$betaOpen}\nbeta();\n{$betaClose}\n");

    $features = [
        'alpha' => new FeatureDefinition(
            key: 'alpha',
            label: 'Alpha',
            sectionMarker: 'alpha',
            markerFiles: ['feature.php'],
        ),
        'beta' => new FeatureDefinition(
            key: 'beta',
            label: 'Beta',
            sectionMarker: 'beta',
            markerFiles: ['feature.php'],
        ),
    ];

    $events = [];
    $script = Chisel::script($this->tempDir);
    FeaturePruner::register(
        $script,
        'modules',
        $features,
        $this->tempDir,
        beforePrune: [
            'alpha' => static function () use (&$events): void {
                $events[] = 'alpha:before';
            },
            'beta' => static function (Chisel $chisel) use (&$events): void {
                $events[] = 'beta:before';
                expect($chisel)->toBeInstanceOf(Chisel::class);
            },
        ],
        afterPrune: [
            'alpha' => static function () use (&$events): void {
                $events[] = 'alpha:after';
            },
            'beta' => static function () use (&$events): void {
                $events[] = 'beta:after';
            },
        ],
    );

    $script->chisel(['modules' => ['alpha']]);

    $content = (string) file_get_contents($file);
    expect($content)->toContain('alpha();')
        ->and($content)->not->toContain('@'.'chisel-alpha')
        ->and($content)->not->toContain('beta();')
        ->and($events)->toBe(['beta:before', 'beta:after']);
});
