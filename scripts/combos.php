<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use App\Chisel\FeatureRegistry;
use App\Chisel\Installer\DependencyValidator;
use App\Chisel\Installer\Profile;

$tier = $argv[1] ?? 'fast';

$allAuth = array_keys(FeatureRegistry::authFeatures());
$allModules = array_keys(FeatureRegistry::optionalModules());

/**
 * Validates whether an answers array respects the starter kit dependency constraints.
 *
 * @param  array{auth_features: list<string>, optional_modules: list<string>}  $answers
 */
function isValidCombination(array $answers): bool
{
    try {
        DependencyValidator::validate($answers);

        return true;
    } catch (RuntimeException) {
        return false;
    }
}

/**
 * Resolves dependencies for a set of answers so it becomes valid.
 *
 * @param  list<string>  $auth
 * @param  list<string>  $modules
 * @return array{auth_features: list<string>, optional_modules: list<string>}
 */
function resolveDependencies(array $auth, array $modules): array
{
    // If any auth feature is chosen, login is required
    if ($auth !== [] && ! in_array('login', $auth, true)) {
        $auth[] = 'login';
    }

    // Reporting requires audit-trails, user-management, authorization
    if (in_array('reporting', $modules, true)) {
        if (! in_array('audit-trails', $modules, true)) {
            $modules[] = 'audit-trails';
        }
        if (! in_array('user-management', $modules, true)) {
            $modules[] = 'user-management';
        }
        if (! in_array('authorization', $modules, true)) {
            $modules[] = 'authorization';
        }
    }

    // Settings, user-management, audit-trails require authorization
    if (
        (in_array('settings', $modules, true) || in_array('user-management', $modules, true) || in_array('audit-trails', $modules, true))
        && ! in_array('authorization', $modules, true)
    ) {
        $modules[] = 'authorization';
    }

    // Authorization and notifications require login
    if (
        (in_array('authorization', $modules, true) || in_array('notifications', $modules, true))
        && ! in_array('login', $auth, true)
    ) {
        $auth[] = 'login';
    }

    return [
        'auth_features' => array_values(array_unique($auth)),
        'optional_modules' => array_values(array_unique($modules)),
    ];
}

/**
 * Generates the "fast" tier combinations.
 *
 * @return list<array{name: string, answers: array{auth_features: list<string>, optional_modules: list<string>}}>
 */
function getFastCombinations(array $allAuth, array $allModules): array
{
    $combos = [];

    // 1. Presets / Profiles
    $combos[] = [
        'name' => 'profile-full',
        'answers' => Profile::answersFor('full'),
    ];
    $combos[] = [
        'name' => 'profile-minimal',
        'answers' => Profile::answersFor('minimal'),
    ];
    $combos[] = [
        'name' => 'profile-default',
        'answers' => Profile::answersFor('default'),
    ];

    // 2. Each single optional module turned off from full (pruning dependencies as needed)
    foreach ($allModules as $module) {
        $modules = array_values(array_diff($allModules, [$module]));
        // If authorization is turned off, dependent modules must also be turned off
        if ($module === 'authorization') {
            $modules = array_values(array_diff($modules, ['settings', 'user-management', 'audit-trails', 'reporting']));
        }
        // If user-management or audit-trails is turned off, reporting must also be turned off
        if ($module === 'user-management' || $module === 'audit-trails') {
            $modules = array_values(array_diff($modules, ['reporting']));
        }

        $combos[] = [
            'name' => "without-module-{$module}",
            'answers' => [
                'auth_features' => $allAuth,
                'optional_modules' => $modules,
            ],
        ];
    }

    // 3. Each single auth feature turned off from full
    foreach ($allAuth as $auth) {
        if ($auth === 'login') {
            // Turning off login disables all auth features and all modules requiring login
            $authFeatures = [];
            $modules = ['localization'];
        } else {
            $authFeatures = array_values(array_diff($allAuth, [$auth]));
            $modules = $allModules;
        }

        $combos[] = [
            'name' => "without-auth-{$auth}",
            'answers' => [
                'auth_features' => $authFeatures,
                'optional_modules' => $modules,
            ],
        ];
    }

    // 4. Standalone modules with minimal auth
    foreach (['authorization', 'localization', 'notifications'] as $standalone) {
        $combos[] = [
            'name' => "standalone-module-{$standalone}",
            'answers' => [
                'auth_features' => ['login', 'logout'],
                'optional_modules' => [$standalone],
            ],
        ];
    }

    // Deduplicate combinations by serialized answers
    $seen = [];
    $unique = [];
    foreach ($combos as $combo) {
        $key = json_encode($combo['answers']);
        if (! isset($seen[$key]) && isValidCombination($combo['answers'])) {
            $seen[$key] = true;
            $unique[] = $combo;
        }
    }

    return $unique;
}

/**
 * Generates pairwise covering combinations.
 *
 * @return list<array{name: string, answers: array{auth_features: list<string>, optional_modules: list<string>}}>
 */
function getPairwiseCombinations(array $allAuth, array $allModules): array
{
    // Features to cover pairwise: all auth features and all optional modules
    $allFeatures = array_merge($allAuth, $allModules);
    $n = count($allFeatures);

    // Build all required valid pairs: array of [featureA_idx, valA, featureB_idx, valB]
    $uncoveredPairs = [];
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            foreach ([0, 1] as $valA) {
                foreach ([0, 1] as $valB) {
                    // Check if this pair is even theoretically valid
                    $candidateAuth = [];
                    $candidateModules = [];
                    $candidateAnswers = [
                        'auth_features' => [],
                        'optional_modules' => [],
                    ];

                    $featA = $allFeatures[$i];
                    $featB = $allFeatures[$j];

                    if ($valA === 1) {
                        if (in_array($featA, $allAuth, true)) {
                            $candidateAnswers['auth_features'][] = $featA;
                        } else {
                            $candidateAnswers['optional_modules'][] = $featA;
                        }
                    }
                    if ($valB === 1) {
                        if (in_array($featB, $allAuth, true)) {
                            $candidateAnswers['auth_features'][] = $featB;
                        } else {
                            $candidateAnswers['optional_modules'][] = $featB;
                        }
                    }

                    // Check for immediate pairwise contradictions:
                    // e.g. featA in auth (not login) = 1, but login = 0
                    if ($valA === 1 && in_array($featA, $allAuth, true) && $featA !== 'login' && $featB === 'login' && $valB === 0) {
                        continue;
                    }
                    if ($valB === 1 && in_array($featB, $allAuth, true) && $featB !== 'login' && $featA === 'login' && $valA === 0) {
                        continue;
                    }

                    // e.g. settings = 1, authorization = 0
                    if ($valA === 1 && in_array($featA, ['settings', 'user-management', 'audit-trails', 'reporting'], true) && $featB === 'authorization' && $valB === 0) {
                        continue;
                    }
                    if ($valB === 1 && in_array($featB, ['settings', 'user-management', 'audit-trails', 'reporting'], true) && $featA === 'authorization' && $valA === 0) {
                        continue;
                    }

                    // e.g. reporting = 1, audit-trails/user-management = 0
                    if ($valA === 1 && $featA === 'reporting' && in_array($featB, ['audit-trails', 'user-management'], true) && $valB === 0) {
                        continue;
                    }
                    if ($valB === 1 && $featB === 'reporting' && in_array($featA, ['audit-trails', 'user-management'], true) && $valA === 0) {
                        continue;
                    }

                    $uncoveredPairs["{$i}:{$valA}:{$j}:{$valB}"] = [$i, $valA, $j, $valB];
                }
            }
        }
    }

    $combos = [];
    $index = 1;

    // Seed with fast combinations to ensure key profiles are covered
    $seedCombos = getFastCombinations($allAuth, $allModules);
    foreach ($seedCombos as $seed) {
        $answers = $seed['answers'];
        $vector = [];
        foreach ($allFeatures as $feat) {
            $vector[] = in_array($feat, $answers['auth_features'], true) || in_array($feat, $answers['optional_modules'], true) ? 1 : 0;
        }

        // Mark covered pairs
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                unset($uncoveredPairs["{$i}:{$vector[$i]}:{$j}:{$vector[$j]}"]);
            }
        }

        $combos[] = $seed;
    }

    // Greedy search for additional test vectors until all valid pairs are covered
    while ($uncoveredPairs !== []) {
        $bestVector = null;
        $bestCoveredCount = -1;
        $bestAnswers = null;

        // Try candidate vectors by attempting to cover the most frequent remaining pairs
        $candidateTries = 0;
        $maxTries = 500;

        foreach ($uncoveredPairs as $pair) {
            [$pI, $pV1, $pJ, $pV2] = $pair;

            $auth = [];
            $modules = [];

            // Random/heuristic assignment with seeded pair fixed
            for ($k = 0; $k < $n; $k++) {
                $feat = $allFeatures[$k];
                $val = ($k === $pI) ? $pV1 : (($k === $pJ) ? $pV2 : mt_rand(0, 1));

                if ($val === 1) {
                    if (in_array($feat, $allAuth, true)) {
                        $auth[] = $feat;
                    } else {
                        $modules[] = $feat;
                    }
                }
            }

            $resolved = resolveDependencies($auth, $modules);
            if (! isValidCombination($resolved)) {
                continue;
            }

            $testVector = [];
            foreach ($allFeatures as $feat) {
                $testVector[] = in_array($feat, $resolved['auth_features'], true) || in_array($feat, $resolved['optional_modules'], true) ? 1 : 0;
            }

            // Count how many uncovered pairs this vector covers
            $count = 0;
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if (isset($uncoveredPairs["{$i}:{$testVector[$i]}:{$j}:{$testVector[$j]}"])) {
                        $count++;
                    }
                }
            }

            if ($count > $bestCoveredCount) {
                $bestCoveredCount = $count;
                $bestVector = $testVector;
                $bestAnswers = $resolved;
            }

            $candidateTries++;
            if ($candidateTries >= $maxTries && $bestCoveredCount > 0) {
                break;
            }
        }

        if ($bestCoveredCount <= 0 || $bestVector === null || $bestAnswers === null) {
            // No further valid assignments can cover remaining pairs (they may be untestable contradictions)
            break;
        }

        // Mark covered
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                unset($uncoveredPairs["{$i}:{$bestVector[$i]}:{$j}:{$bestVector[$j]}"]);
            }
        }

        $combos[] = [
            'name' => 'pairwise-combo-'.count($combos),
            'answers' => $bestAnswers,
        ];
    }

    return array_values($combos);
}

/**
 * Generates all valid combinations.
 *
 * @return list<array{name: string, answers: array{auth_features: list<string>, optional_modules: list<string>}}>
 */
function getFullCombinations(array $allAuth, array $allModules): array
{
    // Auth variations:
    // Either empty auth [], or login with any subset of the remaining 6 auth features (2^6 = 64)
    $authVariations = [[]];
    $otherAuth = array_values(array_diff($allAuth, ['login']));
    $authCount = count($otherAuth);
    $authTotal = 1 << $authCount;

    for ($mask = 0; $mask < $authTotal; $mask++) {
        $subset = ['login'];
        for ($b = 0; $b < $authCount; $b++) {
            if (($mask & (1 << $b)) !== 0) {
                $subset[] = $otherAuth[$b];
            }
        }
        $authVariations[] = $subset;
    }

    // Optional module variations:
    // Total 2^7 = 128 possibilities, filtered by dependency validator
    $moduleVariations = [];
    $modCount = count($allModules);
    $modTotal = 1 << $modCount;

    for ($mask = 0; $mask < $modTotal; $mask++) {
        $subset = [];
        for ($b = 0; $b < $modCount; $b++) {
            if (($mask & (1 << $b)) !== 0) {
                $subset[] = $allModules[$b];
            }
        }

        // Test validity with full auth
        if (isValidCombination(['auth_features' => $allAuth, 'optional_modules' => $subset])) {
            $moduleVariations[] = $subset;
        }
    }

    $combos = [];
    $id = 1;

    foreach ($authVariations as $auth) {
        foreach ($moduleVariations as $modules) {
            $answers = [
                'auth_features' => $auth,
                'optional_modules' => $modules,
            ];

            if (isValidCombination($answers)) {
                $combos[] = [
                    'name' => "combo-{$id}",
                    'answers' => $answers,
                ];
                $id++;
            }
        }
    }

    return $combos;
}

$combinations = match ($tier) {
    'fast' => getFastCombinations($allAuth, $allModules),
    'pairwise' => getPairwiseCombinations($allAuth, $allModules),
    'full' => getFullCombinations($allAuth, $allModules),
    default => throw new InvalidArgumentException("Unknown tier [{$tier}]. Supported tiers: fast, pairwise, full."),
};

$shard = isset($argv[2]) && is_numeric($argv[2]) ? (int) $argv[2] : null;
$totalShards = isset($argv[3]) && is_numeric($argv[3]) ? (int) $argv[3] : null;

if ($shard !== null && $totalShards !== null && $totalShards > 0 && $shard > 0) {
    $sharded = [];
    foreach ($combinations as $index => $combo) {
        if (($index % $totalShards) === ($shard - 1)) {
            $sharded[] = $combo;
        }
    }
    $combinations = $sharded;
}

$matrix = [
    'include' => array_map(function (array $combo): array {
        return [
            'name' => $combo['name'],
            'answers' => json_encode($combo['answers'], JSON_UNESCAPED_SLASHES),
        ];
    }, $combinations),
];

echo json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
