# App/Chisel feature pruning guidelines

- Infinity Starter Kit is modular and powered by Laravel Chisel (`php artisan install:features`).
- Users can selectively install features during setup, and unselected features are automatically pruned.
- Features are authoritatively defined in `App\Chisel\FeatureRegistry` and mirrored in `chisel-paths.php`.

## Feature Registration

- Whenever a change introduces an optional feature (or modifies existing ones such as Audit Trails, Reporting, Notifications, User Management, Settings, Authorization, Localization, 2FA, Email Verification, Registration):
  - Register all associated files, directories, routes, models, controllers, actions, and tests in `App\Chisel\FeatureRegistry`.
  - Maintain clean boundaries so that pruning a feature does not leave orphan references, dangling imports, or broken service provider registrations.
  - If a feature has dependencies on other features (e.g. `registration` requires `login`), declare that dependency in `FeatureRegistry`.

## Dry-run and Installer Verification

- Chisel supports dry-run mode: `php artisan install:features --dry-run`.
- Dry-run mode resolves features, validates dependencies, and previews planned mutations with **zero filesystem or database side effects**.
- Always verify feature combinations (e.g. `bash scripts/verify-combo.sh '{"auth_features":["login","logout"],"optional_modules":[]}'`) when modifying installer-related logic or feature paths to ensure full test suite coverage for pruning flows.

## Initial Generation vs. Runtime Feature Disabling

- **Initial project generation (`install:features`)**: An unselected optional feature should not leave its exclusive Composer dependency or exclusive configuration/migrations in the generated application. Chisel's `ComposerManifestPruner` removes unselected packages and syncs `composer.lock`.
- **Runtime feature disabling**: Disabling a feature in an already-generated application (via runtime settings, permissions, or feature flags) must **never** automatically uninstall Composer packages or drop database tables. Runtime disabling preserves infrastructure and stored data.
