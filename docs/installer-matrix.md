# Installer Scenario Matrix & Test Inventory

## Purpose

The Infinity Starter Kit is powered by Laravel Chisel (`php artisan install:features`), allowing developers to select only the features they need while automatically pruning unused code, migrations, models, controllers, frontend views, npm packages, and composer dependencies.

This scenario matrix provides comprehensive, maintainable test coverage for the starter kit installer across all lifecycle phases, feature combinations, edge cases, failure modes, and recovery paths.

---

## Test Architecture & Layers

The test suite is structured into three distinct layers:

1. **Unit Tests (`tests/Unit/Chisel/`)**
   - High-speed, focused unit assertions on pure logic.
   - Covers context resolution, dependency validation, preflight checks, installation planning, directory/JSON/manifest pruning, state persistence, performance budgets, and scenario matrix integrity.

2. **Feature / Integration Tests (`tests/Feature/Chisel/`)**
   - Command orchestration and filesystem sandboxes.
   - Covers `install:features` interactive prompts, JSON answers, CLI options, dry-run previews, pairwise feature combinations (`FeatureCombinationTest`), feature contract compliance (`FeatureContractTest`), and documentation sync.

3. **Installer E2E (`tests/Feature/Chisel/GeneratedProjectLifecycleTest.php` & `ComposerCreateProjectLifecycleTest.php`)**
   - Real generated application lifecycle against an isolated temporary project.
   - Executes the real `chisel.php` script, real filesystem pruning, Composer manifest modification, Bun lockfile update, database migrations, admin setup, frontend asset compilation, PHPStan static analysis, and smoke HTTP request verification without mocks.
   - Tagged with `@group installer-e2e`.

---

## Running Scenarios Locally

### Canonical Installer E2E Suite
Run the canonical installer E2E suite:
```bash
composer test:installer
```

### Generated Application Smoke Suite
Run the smoke validation pipeline across representative profiles (minimal, default, full):
```bash
composer test:smoke
```

### Run a Specific Scenario
You can target a specific scenario or profile using Pest's `--filter` option:
```bash
# Run a specific scenario in E2E lifecycle
vendor/bin/pest tests/Feature/Chisel/GeneratedProjectLifecycleTest.php --filter="minimal"

# Run by scenario ID
vendor/bin/pest --group=installer-e2e --filter="full"

# Run smoke tests for a single profile
composer test:smoke -- --filter="minimal"
```

### Run Scenario Matrix Integrity Checks
Verify that all registered features, auth capabilities, installer phases, and dependencies are covered:
```bash
vendor/bin/pest tests/Unit/Chisel/InstallerScenarioMatrixTest.php
```

### Run Fast Feature Combination Sandboxes
Run the 40+ pairwise feature combination tests:
```bash
vendor/bin/pest tests/Feature/Chisel/FeatureCombinationTest.php
```

---

## Scenario Matrix Catalog

| Scenario ID | Name | Category | Auth Features | Optional Modules | Configuration / Environment | Expected Result | Test Level |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `minimal` | Minimal Profile | Profile | `login`, `logout` | _(none)_ | Default | Success (Exit 0), all optional modules pruned | E2E / Smoke |
| `default` | Default Profile | Profile | Standard auth (6 features) | `authorization`, `settings`, `user-management`, `notifications` | Admin credentials provided | Success (Exit 0), admin created, secret redacted | E2E / Smoke |
| `full` | Full Profile | Profile | All auth (incl. `two-factor`) | All optional modules (7 modules) | Admin credentials provided | Success (Exit 0), full stack verified | E2E / Smoke |
| `auth-minimal` | Auth-Minimal Profile | Profile | `login`, `logout` | All optional modules (7 modules) | Default | Success (Exit 0), modules work with minimal auth | Feature / E2E |
| `no-modules` | No Optional Modules | Profile | All auth (incl. `two-factor`) | _(none)_ | Default | Success (Exit 0), clean auth-only application | Feature / E2E |
| `dependency-heavy` | Dependency-Heavy | Profile | `login`, `logout` | `authorization`, `user-management`, `audit-trails`, `reporting` | Chained dependency resolution | Success (Exit 0), all transitives retained | E2E / Feature |
| `module-authorization` | Authorization Alone | Module | `login`, `logout` | `authorization` | Standalone module | Success (Exit 0), roles & permissions retained | Feature / Integration |
| `module-settings` | Settings Module | Module | `login`, `logout` | `authorization`, `settings` | Required dependency `authorization` | Success (Exit 0), settings schema & UI retained | Feature / Integration |
| `module-user-management`| User Management Module | Module | `login`, `logout` | `authorization`, `user-management` | Required dependency `authorization` | Success (Exit 0), user directory retained | Feature / Integration |
| `module-localization` | Localization Module | Module | `login`, `logout` | `localization` | Zero module dependencies | Success (Exit 0), `@erag` packages retained | Feature / Integration |
| `module-notifications` | Notifications Module | Module | `login`, `logout` | `notifications` | Zero module dependencies | Success (Exit 0), notification channels retained | Feature / Integration |
| `module-audit-trails` | Audit Trails Module | Module | `login`, `logout` | `authorization`, `audit-trails` | Required dependency `authorization` | Success (Exit 0), audit logging retained | Feature / Integration |
| `module-reporting` | Reporting Module | Module | `login`, `logout` | `authorization`, `user-management`, `audit-trails`, `reporting` | Full analytics chain | Success (Exit 0), charts & CSV export retained | Feature / Integration |
| `auth-no-registration` | Registration Disabled | Auth | All except `registration` | `authorization`, `user-management` | Invite-only setup | Success (Exit 0), registration route pruned | Feature / Integration |
| `auth-no-email-verification` | Email Verification Disabled | Auth | All except `email-verification` | `authorization` | Direct login flow | Success (Exit 0), verification notice pruned | Feature / Integration |
| `auth-no-password-reset` | Password Reset Disabled | Auth | All except `password-reset` | `authorization` | Managed passwords only | Success (Exit 0), reset routes pruned | Feature / Integration |
| `auth-no-two-factor` | Two-Factor Disabled | Auth | All except `two-factor` | `authorization` | Single-factor auth | Success (Exit 0), 2FA secret columns pruned | Feature / Integration |
| `auth-invalid-dependency` | Invalid Auth Dependency | Auth | `registration` only (no `login`) | _(none)_ | Missing required dependency | Failure (Exit 1), rejected before mutation | Unit / Feature |
| `db-empty` | Empty Database | Database | `login`, `logout` | `authorization` | Clean unmigrated DB | Success (Exit 0), clean schema migration | Feature / E2E |
| `db-migrated` | Migrated Database | Database | `login`, `logout` | `authorization` | Pre-existing schema | Success (Exit 0), data preserved, force migrate | Feature / E2E |
| `db-early-migration-fresh` | Early Migration + Fresh | Database | `login`, `logout` | `authorization` | `--fresh` flag passed | Success (Exit 0), pruned tables cleanly dropped | E2E / Feature |
| `admin-valid-credentials` | Admin Valid Credentials | Admin | `login`, `logout` | `authorization` | Valid admin options | Success (Exit 0), user created, password redacted | E2E / Feature |
| `admin-no-credentials` | Auth Without Admin | Admin | `login`, `logout` | `authorization` | Zero admin credentials | Success (Exit 0), roles created, 0 users | E2E / Feature |
| `admin-cli-options` | Admin via CLI Flags | Admin | `login`, `logout` | `authorization` | `--admin-*` flags | Success (Exit 0), options parsed properly | Feature / Integration |
| `admin-answers-payload` | Admin via Answers JSON | Admin | `login`, `logout` | `authorization` | `admin` key in JSON | Success (Exit 0), payload parsed properly | Feature / Integration |
| `failure-preflight` | Failure at Preflight | Failure | `login`, `logout` | `authorization` | `CHISEL_TEST_FAIL_STAGE=preflight` | Failure (Exit 1), zero filesystem mutations | Feature / Unit |
| `failure-dependency-validation` | Missing Dependency Failure | Failure | `login`, `logout` | `reporting` only | Missing prerequisite modules | Failure (Exit 1), rejected with clear message | Unit / Feature |
| `failure-post-chisel-resume` | Post-Chisel Recovery | Failure | `login`, `logout` | `authorization` | Deterministic fail + retry | Failure on run 1, clean recovery on run 2 | E2E / Feature |
| `failure-post-migration` | Post-Migration Failure | Failure | `login`, `logout` | `authorization` | `CHISEL_TEST_FAIL_STAGE=post_migration` | Failure (Exit 1), state saved post-migration | Feature / Unit |
| `failure-cleanup` | Cleanup Phase Failure | Failure | `login`, `logout` | `authorization` | `CHISEL_TEST_FAIL_STAGE=cleanup` | Failure (Exit 1), error recorded in cleanup | Feature / Unit |
| `failure-conflicting-retry` | Conflicting Retry Rejection | Failure | `login`, `logout` | `authorization`, `settings` | Retrying with altered answers | Failure (Exit 1), conflict details printed | Feature / Unit |
| `env-no-node` | No Node / Bun Mode | Environment | `login`, `logout` | `authorization`, `user-management`, `audit-trails`, `reporting` | `LARAVEL_INSTALLER_NO_NODE=true` | Success (Exit 0), skips node steps, manifests ok | E2E / Feature |
| `env-deferred-hooks` | Deferred Hooks Mode | Environment | `login`, `logout` | `authorization` | `LARAVEL_INSTALLER_DEFER_HOOKS=true` | Success (Exit 0), graceful exit | Feature / Integration |
| `env-missing-chisel` | Missing Chisel Files | Environment | `login`, `logout` | _(none)_ | `chisel.php` absent | Success (Exit 0), graceful no-op | Feature / Integration |

---

## Behavioral Coverage Inventory

### 1. Auth Capabilities
- **Minimal Auth (`login`, `logout`)**: Covered in `minimal`, `auth-minimal`, and individual module tests.
- **Full Auth (7 capabilities)**: Covered in `full`, `no-modules`, and `ComposerCreateProjectLifecycleTest` (Scenario A).
- **Registration Disabled**: Covered in `auth-no-registration` (`FeatureCombinationTest` & `InstallFeaturesCommandTest`).
- **Email Verification Disabled**: Covered in `auth-no-email-verification`.
- **Password Reset Disabled**: Covered in `auth-no-password-reset`.
- **Two-Factor Disabled**: Covered in `auth-no-two-factor`.
- **Invalid Auth Combinations**: Covered in `auth-invalid-dependency` (`DependencyValidatorTest` & `InstallFeaturesCommandTest`).

### 2. Optional Modules
- **Authorization**: Verified enabled in `module-authorization`, disabled in `minimal`.
- **Settings**: Verified enabled with `authorization` in `module-settings`, disabled in `minimal`.
- **User Management**: Verified enabled with `authorization` in `module-user-management`, disabled in `minimal`.
- **Localization**: Verified in `module-localization` (retains `erag/laravel-lang-sync-inertia` and `@erag/lang-sync-inertia`), disabled in `minimal`.
- **Notifications**: Verified in `module-notifications`, disabled in `minimal`.
- **Audit Trails**: Verified in `module-audit-trails`, disabled in `minimal`.
- **Reporting**: Verified in `module-reporting` (retains `spatie/laravel-data` and reporting queries), disabled in `minimal`.

### 3. Representative Combined Profiles
- Exercised in true E2E tests:
  - `minimal` (core auth only, zero optional modules);
  - `default` / `standard` (recommended configuration);
  - `full` (all auth + all optional modules);
  - `dependency-heavy` (reporting + audit-trails + user-management + authorization);
  - `no-modules` (full auth, zero optional modules);
  - `auth-minimal` (minimal auth, full optional modules).

### 4. Interactive and Non-Interactive Entry Points
- Interactive mode: Covered in `InstallFeaturesCommandTest` using test input mock.
- `--answers` JSON string: Covered in `InstallFeaturesCommandTest` and `GeneratedProjectLifecycleTest`.
- Admin provisioning: Covered in `SetupAdminUserCommandTest` and `GeneratedProjectLifecycleTest`.
- Deferred hooks: Covered in `env-deferred-hooks` and `InstallerContextResolverTest`.

### 5. Fresh Project & Real Filesystem Lifecycle
- Tested in `GeneratedProjectLifecycleTest`.
- Reflink-based copy of real repository including lockfiles and dependencies.
- Real execution of `chisel.php` and `chisel-paths.php`.
- Complete verification of installer overhead cleanup (`assertCleanOfInstallerArtifacts`).

### 6. Dependency Integrity
- Verified in `GeneratedProjectLifecycleTest` and `ComposerLockSynchronizerTest`:
  - `composer.json` contains only required dependencies;
  - `package.json` contains only required dependencies;
  - Lockfile synchronization keeps lockfiles clean;
  - `composer install` succeeds from generated state;
  - `bun install` / `bun run build` succeeds from generated state.

### 7. Generated Application Validation
- Handled by `GeneratedApplicationSmokePipeline`:
  - `validateApplicationBoot`: `artisan about` executes cleanly;
  - `validateDatabase`: `migrate:status` shows zero pending migrations;
  - `validateRoutesAndConfig`: `route:list` and `config:show` succeed;
  - `validateFrontend`: production asset manifest and bundle verified;
  - `validateTypeScript`: `tsc` / `test:types` passes without type errors;
  - `validateHttpRequests`: in-process Inertia request returns HTTP 200 with X-Inertia headers;
  - `validatePerformanceBudgets`: bundle sizes and database query counts stay within defined budgets.

### 8. Database Lifecycle
- Fresh database: Tested in standard fixture runs.
- Existing migrated database: Tested in `GeneratedProjectLifecycleTest`.
- Early migration with `--fresh`: Tested in `db-early-migration-fresh` to prevent orphaned pruned tables.

### 9. Administrator Setup
- Valid credentials: Creates Super Admin role, creates admin user, assigns role.
- Without credentials: Creates Super Admin role, leaves zero users.
- Secret redaction: Admin password must never appear in stdout, stderr, or `laravel.log`.

### 10. Failure and Recovery Paths
- Preflight failure: Tested in `failure-preflight`.
- Dependency validation failure: Tested in `failure-dependency-validation`.
- Post-chisel failure & recovery: Checkpoint saved without secrets; resume succeeds and cleans up state (tested in `failure-post-chisel-resume` in `GeneratedProjectLifecycleTest`).
- Post-migration failure: Checkpoint saved after database migrations; tested in `failure-post-migration`.
- Cleanup phase failure: Error recorded during final cleanup; tested in `failure-cleanup`.
- Conflicting retry: Conflicting answers on resume are rejected with detailed diff (tested in `failure-conflicting-retry`).

### 11. Environment Modes
- Normal mode: Full compilation and linting.
- `LARAVEL_INSTALLER_NO_NODE=true`: Tested in `env-no-node` (`GeneratedProjectLifecycleTest`).
- `LARAVEL_INSTALLER_DEFER_HOOKS=true`: Tested in `env-deferred-hooks`.
- Missing chisel files: Graceful exit tested in `env-missing-chisel`.

### 12. Security and Privacy Invariants
- Admin passwords are never printed or stored unencrypted.
- `.installer-state.json` never persists plaintext credentials.
- Error logs redact secrets on injected failure exceptions.
- Test fixtures are cleaned up in `finally` blocks.
