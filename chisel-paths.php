<?php

declare(strict_types=1);

if (! class_exists('App\Chisel\FeatureRegistry')) {
    require_once __DIR__.'/vendor/autoload.php';
}

use App\Chisel\FeatureRegistry;

/**
 * Framework-specific paths and definitions for Infinity Starter Kit (React + Inertia + TypeScript).
 *
 * Sourced authoritatively from App\Chisel\FeatureRegistry.
 */
return FeatureRegistry::toPathsArray();
