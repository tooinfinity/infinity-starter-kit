<?php

declare(strict_types=1);

test('pairwise combinations cover every achievable pair derived from all valid combinations', function (): void {
    // Generate full and pairwise matrix outputs
    $fullOutput = shell_exec('php scripts/combos.php full');
    $pairwiseOutput = shell_exec('php scripts/combos.php pairwise');

    expect(is_string($fullOutput) && $fullOutput !== '')->toBeTrue()
        ->and(is_string($pairwiseOutput) && $pairwiseOutput !== '')->toBeTrue();

    /** @var array{include: list<array{name: string, answers: string}>} $fullMatrix */
    $fullMatrix = json_decode((string) $fullOutput, true, 512, JSON_THROW_ON_ERROR);
    /** @var array{include: list<array{name: string, answers: string}>} $pairwiseMatrix */
    $pairwiseMatrix = json_decode((string) $pairwiseOutput, true, 512, JSON_THROW_ON_ERROR);

    // Full tier emits batched answers as JSON arrays
    $allValidCombos = [];
    foreach ($fullMatrix['include'] as $batch) {
        /** @var list<array{auth_features: list<string>, optional_modules: list<string>}> $batchCombos */
        $batchCombos = json_decode($batch['answers'], true, 512, JSON_THROW_ON_ERROR);
        foreach ($batchCombos as $combo) {
            $allValidCombos[] = $combo;
        }
    }

    $allPairwiseCombos = [];
    foreach ($pairwiseMatrix['include'] as $item) {
        /** @var array{auth_features: list<string>, optional_modules: list<string>} $combo */
        $combo = json_decode($item['answers'], true, 512, JSON_THROW_ON_ERROR);
        $allPairwiseCombos[] = $combo;
    }

    $allFeatures = [
        'login',
        'logout',
        'registration',
        'email-verification',
        'password-reset',
        'password-update',
        'two-factor',
        'authorization',
        'settings',
        'user-management',
        'localization',
        'notifications',
        'audit-trails',
    ];
    $n = count($allFeatures);

    /** @var array<string, bool> $validPairs */
    $validPairs = [];
    foreach ($allValidCombos as $combo) {
        $vector = [];
        foreach ($allFeatures as $feat) {
            $vector[] = in_array($feat, $combo['auth_features'], true) || in_array($feat, $combo['optional_modules'], true) ? 1 : 0;
        }

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $validPairs["{$i}:{$vector[$i]}:{$j}:{$vector[$j]}"] = true;
            }
        }
    }

    /** @var array<string, bool> $pairwisePairs */
    $pairwisePairs = [];
    foreach ($allPairwiseCombos as $combo) {
        $vector = [];
        foreach ($allFeatures as $feat) {
            $vector[] = in_array($feat, $combo['auth_features'], true) || in_array($feat, $combo['optional_modules'], true) ? 1 : 0;
        }

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $pairwisePairs["{$i}:{$vector[$i]}:{$j}:{$vector[$j]}"] = true;
            }
        }
    }

    $uncoveredValidPairs = array_diff_key($validPairs, $pairwisePairs);

    expect($uncoveredValidPairs)->toBe([]);
});

test('pairwise combinations partitioned across shards cover every achievable pair in union', function (): void {
    $totalShards = 4;
    $allFeatures = [
        'login',
        'logout',
        'registration',
        'email-verification',
        'password-reset',
        'password-update',
        'two-factor',
        'authorization',
        'settings',
        'user-management',
        'localization',
        'notifications',
        'audit-trails',
    ];
    $n = count($allFeatures);

    // Collect combinations across all shards
    $shardedCombos = [];
    for ($shard = 1; $shard <= $totalShards; $shard++) {
        $output = shell_exec("php scripts/combos.php pairwise {$shard} {$totalShards}");
        expect(is_string($output) && $output !== '')->toBeTrue();

        /** @var array{include: list<array{name: string, answers: string}>} $matrix */
        $matrix = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        foreach ($matrix['include'] as $item) {
            /** @var array{auth_features: list<string>, optional_modules: list<string>} $combo */
            $combo = json_decode($item['answers'], true, 512, JSON_THROW_ON_ERROR);
            $shardedCombos[] = $combo;
        }
    }

    // Generate full matrix to compute all achievable valid pairs
    $fullOutput = shell_exec('php scripts/combos.php full');
    expect(is_string($fullOutput) && $fullOutput !== '')->toBeTrue();

    /** @var array{include: list<array{name: string, answers: string}>} $fullMatrix */
    $fullMatrix = json_decode((string) $fullOutput, true, 512, JSON_THROW_ON_ERROR);

    /** @var array<string, bool> $validPairs */
    $validPairs = [];
    foreach ($fullMatrix['include'] as $batch) {
        /** @var list<array{auth_features: list<string>, optional_modules: list<string>}> $batchCombos */
        $batchCombos = json_decode($batch['answers'], true, 512, JSON_THROW_ON_ERROR);
        foreach ($batchCombos as $combo) {
            $vector = [];
            foreach ($allFeatures as $feat) {
                $vector[] = in_array($feat, $combo['auth_features'], true) || in_array($feat, $combo['optional_modules'], true) ? 1 : 0;
            }

            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $validPairs["{$i}:{$vector[$i]}:{$j}:{$vector[$j]}"] = true;
                }
            }
        }
    }

    /** @var array<string, bool> $shardedPairs */
    $shardedPairs = [];
    foreach ($shardedCombos as $combo) {
        $vector = [];
        foreach ($allFeatures as $feat) {
            $vector[] = in_array($feat, $combo['auth_features'], true) || in_array($feat, $combo['optional_modules'], true) ? 1 : 0;
        }

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $shardedPairs["{$i}:{$vector[$i]}:{$j}:{$vector[$j]}"] = true;
            }
        }
    }

    $uncoveredValidPairs = array_diff_key($validPairs, $shardedPairs);

    expect($uncoveredValidPairs)->toBe([]);
});
