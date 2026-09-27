<?php

declare(strict_types=1);

test('all chisel markers in repository are well-formed and properly paired', function (): void {
    $command = 'git ls-files';
    $output = shell_exec($command);
    expect($output)->not->toBeNull();

    $files = array_filter(explode("\n", mb_trim((string) $output)));
    $errors = [];
    $allTags = [];

    foreach ($files as $file) {
        if (! file_exists(base_path($file)) || is_dir(base_path($file)) || $file === 'chisel.php' || $file === 'README.md') {
            continue;
        }

        $content = file_get_contents(base_path($file));
        if ($content === false || (! str_contains($content, '@chisel-') && ! str_contains($content, '@end-chisel-'))) {
            continue;
        }

        preg_match_all('/(?:\{\{--|\{?\/\*|<!--)\s*@chisel-([a-zA-Z0-9_-]+)\s*(?:--\}\}|\*\/\}?|-->)/', $content, $opens, PREG_OFFSET_CAPTURE);
        preg_match_all('/(?:\{\{--|\{?\/\*|<!--)\s*@end-chisel-([a-zA-Z0-9_-]+)\s*(?:--\}\}|\*\/\}?|-->)/', $content, $closes, PREG_OFFSET_CAPTURE);

        $markers = [];
        foreach ($opens[1] as [$tag, $offset]) {
            $markers[] = ['type' => 'open', 'tag' => $tag, 'offset' => $offset];
            $allTags[$tag] = true;
        }

        foreach ($closes[1] as [$tag, $offset]) {
            $markers[] = ['type' => 'close', 'tag' => $tag, 'offset' => $offset];
            $allTags[$tag] = true;
        }

        usort($markers, fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        $stack = [];
        $lastType = null;
        $lastTag = null;

        foreach ($markers as $marker) {
            if ($marker['type'] === $lastType && $marker['tag'] === $lastTag) {
                $errors[] = sprintf('Consecutive %s markers for @%s in %s', $marker['type'], $marker['tag'], $file);
            }

            $lastType = $marker['type'];
            $lastTag = $marker['tag'];

            if ($marker['type'] === 'open') {
                $stack[] = $marker;
            } else {
                if ($stack === []) {
                    $errors[] = sprintf('Unmatched closing marker @end-chisel-%s in %s', $marker['tag'], $file);
                } else {
                    $last = array_pop($stack);
                    if ($last['tag'] !== $marker['tag']) {
                        $errors[] = sprintf('Mismatched marker pair in %s: opened @chisel-%s, closed @end-chisel-%s', $file, $last['tag'], $marker['tag']);
                    }
                }
            }
        }

        while ($stack !== []) {
            $unclosed = array_pop($stack);
            $errors[] = sprintf('Unclosed @chisel-%s in %s', $unclosed['tag'], $file);
        }
    }

    expect($errors)->toBeEmpty();
});

test('all marker tags have corresponding handlers in chisel.php', function (): void {
    $command = 'git ls-files';
    $output = shell_exec($command);
    $files = array_filter(explode("\n", mb_trim((string) $output)));

    $tagsInRepo = [];
    foreach ($files as $file) {
        if (! file_exists(base_path($file)) || is_dir(base_path($file)) || $file === 'chisel.php' || $file === 'README.md') {
            continue;
        }

        $content = (string) file_get_contents(base_path($file));
        preg_match_all('/@chisel-([a-zA-Z0-9_-]+)/', $content, $matches);
        foreach ($matches[1] as $tag) {
            $tagsInRepo[$tag] = true;
        }
    }

    $chiselScript = (string) file_get_contents(base_path('chisel.php'));

    foreach (array_keys($tagsInRepo) as $tag) {
        $hasHandler = str_contains($chiselScript, "'{$tag}'")
            || str_contains($chiselScript, "\"{$tag}\"");

        expect($hasHandler)->toBeTrue("Marker tag '{$tag}' found in codebase has no handler in chisel.php");
    }
});

test('all static files and directories declared in chisel-paths.php exist in the repository', function (): void {
    /** @var array<string, mixed> $paths */
    $paths = require base_path('chisel-paths.php');

    $checkPaths = function (mixed $value, string $keyPath) use (&$checkPaths): void {
        if (is_string($value)) {
            // If it ends with an extension or contains slash and is not a package name
            if (str_contains($value, '/') && ! str_contains($keyPath, 'package') && ! str_contains($keyPath, 'dependencies')) {
                expect(file_exists(base_path($value)))->toBeTrue("Declared path '{$value}' in {$keyPath} does not exist on disk.");
            }
        } elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                $checkPaths($v, "{$keyPath}.{$k}");
            }
        }
    };

    foreach ($paths as $feature => $data) {
        if ($feature === 'dependencies') {
            continue;
        }

        $checkPaths($data, $feature);
    }
});
