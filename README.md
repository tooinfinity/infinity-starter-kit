# Infinity Starter Kit (Laravel + Inertia + React)

A full-featured, modular Laravel starter kit powered by **[Laravel Chisel](https://github.com/laravel/chisel)**, **[Laravel Fortify](https://laravel.com/docs/fortify)**, and **[Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)**.

Designed for speed and cleanliness: choose your features during `composer create-project`, and Chisel automatically prunes unused backend routes, controllers, actions, Inertia pages, traits, model interfaces, and Pest tests.

---

## ⚡ Tech Stack

- **Framework**: [Laravel 13](https://laravel.com) (PHP 8.5+)
- **Frontend SPA**: [Inertia.js v3](https://inertiajs.com) + [React 19](https://react.dev)
- **TypeScript & Routing**: [Laravel Wayfinder](https://github.com/laravel/wayfinder) (`@/actions`, `@/routes`)
- **Styling**: [Tailwind CSS v4](https://tailwindcss.com) + Radix UI primitives + Lucide Icons
- **Bundler**: [Vite-Plus](https://vite.dev) / Bun
- **Authentication**: [Laravel Fortify](https://laravel.com/docs/fortify)
- **Authorization / RBAC**: [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- **Feature Pruning**: [Laravel Chisel](https://github.com/laravel/chisel)
- **Testing**: [Pest 5](https://pestphp.com)

---

## 🚀 Quick Start

### 1. Create a New Project

```bash
composer create-project tooinfinity/infinity-starter-kit my-app
```

During setup, the `post-create-project-cmd` hook will automatically:
1. Generate your application encryption key.
2. Initialize your local database (`database/database.sqlite`).
3. Run database migrations.
4. Trigger the interactive **`php artisan install:features`** command powered by **Chisel**.

### 2. Select Your Features

When prompted:

```text
Which authentication features would you like to enable?
 [x] Registration
 [x] Email verification
 [x] Two-factor authentication

Which authorization features would you like to enable?
 [x] Spatie Roles & Permissions (spatie/laravel-permission)
```

Select the features you want using `Space`, then press `Enter`.

### 3. Set Up Authorization (if enabled)

```bash
php artisan authorization:setup   # Creates permissions + Super Admin role
php artisan admin:setup           # Creates admin user interactively
```

### 4. Start Development

```bash
cd my-app
composer run dev
```

---

## 🛠️ Implemented Modules

### 🔐 Authentication Module

| Feature | Description | Chisel Pruning |
| :--- | :--- | :--- |
| **Registration** | User registration form, routes, and user creation action. | Removes `/register` route, registration page, and login page register links. |
| **Email Verification** | Native Fortify verification flow (`MustVerifyEmail`), verification notice page, resend notifications. | Strips `MustVerifyEmail` interface, removes verification controllers, views, and tests. |
| **Two-Factor Authentication** | TOTP / QR codes, recovery codes, security settings page, and 2FA challenge flow. | Strips `TwoFactorAuthenticatable` trait, removes 2FA routes, settings UI, controllers, and tests. |
| **Account & Security** | Login/logout, password reset, profile updates, password change, appearance settings. | **Core** — always retained. |

---

### 🛡️ Authorization & RBAC Module

A **Policy-Free** role-based access control system powered by `spatie/laravel-permission`, PHP string-backed enums, and Laravel Gates.

#### Architecture

```text
Permission enum (source of truth)
        │
        ▼
Spatie Permission models
        │
Gate::before() ── Super Admin bypass
        │
Form Request authorize() ── Per-endpoint access control
        │
Inertia shared props ── Frontend authorization data
        │
useAuthorization() hook / <Can> component ── UI helpers
```

#### Key Design Decisions

- **No Policies** — All authorization uses `Gate::before()` for super-admin bypass, Spatie permission checks, and Form Request `authorize()` methods.
- **PHP Enums** — `App\Enums\Permission` and `App\Enums\Role` are the single source of truth for permission/role identifiers. No magic strings.
- **Two Setup Commands** — Separation of concerns: `authorization:setup` manages permissions/roles, `admin:setup` manages users.
- **Frontend UI Helpers** — `useAuthorization()` hook and `<Can>` component read shared Inertia props. These are UI helpers only; server-side authorization is the actual security boundary.

#### Permission Enum

```php
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
}
```

Add your own permissions by extending the enum. Run `php artisan authorization:setup` to synchronize.

#### Role Enum

```php
enum Role: string
{
    case SuperAdmin = 'super-admin';
}
```

Only `super-admin` is included in the starter kit. Add application-specific roles as needed.

#### Super Admin Bypass

Configured in `AppServiceProvider` via `Gate::before()`:

```php
Gate::before(function (User $user, string $ability): ?true {
    if ($user->hasRole(Role::SuperAdmin->value)) {
        return true;
    }
    return null;
});
```

#### Form Request Authorization

Use the `Permission` enum in Form Request `authorize()` methods:

```php
public function authorize(): bool
{
    return $this->user()?->can(Permission::UsersCreate->value) ?? false;
}
```

#### Frontend Authorization

**useAuthorization hook:**

```tsx
const { can, canAny, canAll, hasRole } = useAuthorization();

if (can('users.create')) { /* ... */ }
if (canAny(['users.update', 'users.delete'])) { /* ... */ }
if (hasRole('super-admin')) { /* ... */ }
```

**Can component:**

```tsx
<Can permission="users.create">
    <Button>Create User</Button>
</Can>

<Can permissions={['users.update', 'users.delete']} mode="any">
    <Button>Manage Users</Button>
</Can>
```

#### Chisel Pruning

When authorization is disabled, Chisel removes:
- `HasRoles` trait from `User` model
- `Gate::before()` from `AppServiceProvider`
- Authorization shared props from `HandleInertiaRequests`
- `config/permission.php` and Spatie migrations
- `app/Enums/Permission.php` and `app/Enums/Role.php`
- Both setup commands
- Frontend hook, `<Can>` component, and authorization types
- All authorization tests

---

### 📊 Reporting & Analytics Module

A **production-ready, read-only** reporting engine designed to extract actionable insights directly from existing application models (`users` and `audit_trails`) without redundant tables or schema overhead.

#### Implemented Reports

| Report | Path | Metrics & Visualizations |
| :--- | :--- | :--- |
| **User Activity & Growth** | `/reports/users` | Total users, active/inactive counts, period registrations, daily registration trend SVG chart, and filterable/sortable user listing. |
| **Audit Trail Activity** | `/reports/audit` | Total audit events, active actors, top event & resource, event distribution breakdown, daily volume trend chart, and audit log table. |

#### Key Design Decisions

- **Strictly Read-Only** — Directly queries existing application tables; no parallel database models, snapshots, or migrations.
- **Dedicated Query Services** — Complex aggregations and filtering live in `App\Queries\Reporting`, keeping controllers clean and invokable.
- **Spatie Data Transfer Objects** — Typed contracts (`ReportSummaryCardData`, `ReportTimeSeriesPointData`, `ReportBreakdownItemData`, `ReportMetadataData`) serialize seamlessly to Inertia.
- **Zero-Dependency Accessible Visualizations** — Custom SVG/CSS charts featuring interactive tooltips, full keyboard/screen-reader accessibility, RTL layout support, and a companion tabular view switch.
- **Secure Streaming CSV Export** — Memory-efficient cursor streaming (`cursor()`), Excel UTF-8 BOM (`\xEF\xBB\xBF`), and formula injection sanitization (`=`, `+`, `-`, `@`, `\t`, `\r` neutralized).
- **Granular RBAC Permissions** — Protected via `Permission::ReportsView` (`reports.view`) and `Permission::ReportsExport` (`reports.export`).
- **Trilingual Localization** — Full translations available in English (`en`), French (`fr`), and Arabic (`ar`).

#### Chisel Pruning

When reporting is unselected in Chisel, all 25 reporting files (controllers, queries, DTOs, translations, frontend components, pages, and tests) are cleanly deleted and routes are removed.

---

## 🧩 Chisel-Based Optional Module System

The Infinity Starter Kit features a deterministic, dependency-aware module composition and removal system built on **Laravel Chisel**. The system operates strictly as a project generation and transformation tool—there are **no runtime module registries or database toggles**. Generated code is clean, ordinary Laravel code.

### Module Categories

- **Core (Permanent)**: Authentication foundation (Laravel Fortify), base layout, Inertia v3 infrastructure, React 19 application shell, shared UI primitives (shadcn/ui), TypeScript configs, and SQLite database foundation. Core functionality can never be pruned.
- **Optional Modules**:
  1. **Authorization** (`authorization`): Spatie Roles & Permissions, PHP enums, Gate super-admin bypass, frontend `<Can>` component and `useAuthorization` hook.
  2. **Settings** (`settings`): Key-value application settings storage, `Setting` model, settings controllers, forms, and pages.
  3. **User Management** (`user-management`): Administrative user directory, creation/edit modals, role assignment, user activation/deactivation.
  4. **Localization** (`localization`): Trilingual support (EN, FR, AR), RTL layout switching, `Locale` enum, `LanguageSelector` component, and `@erag/lang-sync-inertia`.
  5. **Notifications** (`notifications`): Database and email notification center, user preference toggles, notification dropdown and bell.
  6. **Audit Trails** (`audit-trails`): Searchable activity log tracking user actions, IP addresses, user agents, and timestamps.
  7. **Reporting & Analytics** (`reporting`): Read-only dashboards, SVG trend charts, date filtering, and streaming CSV exports.

---

### 🗺️ Dependency Graph & Compatibility Matrix

Modules declare their requirements explicitly. Dependencies are automatically resolved during installation, and reverse dependencies are strictly validated before removal.

```text
Authentication (Core)
    │
    ├── Authorization
    │       │
    │       ├── User Management
    │       │       │
    │       │       └── Reporting
    │       │
    │       └── Reporting
    │
    ├── Settings
    │
    ├── Localization
    │       │
    │       └── Notifications
    │
    └── Audit Trails
            │
            └── Reporting
```

| Module | Identifier | Depends On | Composer Packages | NPM Packages | Permissions |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Authorization** | `authorization` | Core (Authentication) | `spatie/laravel-permission` | — | `authorization.manage` |
| **Settings** | `settings` | Core | — | — | `settings.manage` |
| **User Management** | `user-management` | `authorization` | `spatie/laravel-data` | — | `users.view`, `users.create`, `users.update`, `users.delete`, `users.manage-roles`, `users.manage-password` |
| **Localization** | `localization` | Core | `erag/laravel-lang-sync-inertia` | `@erag/lang-sync-inertia` | — |
| **Notifications** | `notifications` | `localization` | — | — | — |
| **Audit Trails** | `audit-trails` | Core (Authentication) | — | — | `audit.view` |
| **Reporting** | `reporting` | `authorization`, `audit-trails`, `user-management` | `spatie/laravel-data` | — | `reports.view`, `reports.export` |

---

### 📦 Installation Flow

During `composer create-project`, the `post-create-project-cmd` hook triggers `php artisan install:features`, prompting:

```text
Which authentication features would you like to enable?
 [x] Registration
 [x] Email verification
 [x] Two-factor authentication

Which optional modules should be installed?
 [x] Authorization
 [x] Settings
 [x] User Management
 [x] Localization
 [x] Notifications
 [x] Audit Trails
 [x] Reporting
```

#### Non-Interactive Installation

Pass answers as a JSON string via the `--answers` option:

```bash
php artisan install:features --answers='{"auth_features":["registration","two-factor-authentication"],"optional_modules":["authorization","settings","user-management","localization","notifications","audit-trails","reporting"]}' --no-interaction
```

If a module is selected, its required dependencies are automatically included (e.g. selecting `user-management` automatically retains `authorization`). Unselected modules have all exclusive code, routes, permissions, translations, and dependencies cleanly pruned.

---

### 🗑️ Removing an Optional Module

You can safely remove an optional module at any time using the `module:remove` Artisan command:

```bash
php artisan module:remove reporting
```

#### Reverse Dependency Validation

If another installed module depends on the module you wish to remove, removal is safely blocked:

```bash
$ php artisan module:remove audit-trails
ERROR Cannot remove Audit Trails because Reporting depends on it. Remove Reporting first.
```

To remove `audit-trails`, remove `reporting` first.

#### Database Safety Warning

> [!WARNING]
> **Database Data Safety:** Removing a module removes its source code, routes, views, configuration, and dependencies. **It does not automatically destroy production database data or drop tables.** If the module previously migrated tables (e.g. `audit_trails`, `settings`), manage database rollbacks explicitly according to your data retention policies.

---

### ➕ Developer Checklist: Adding a New Optional Module

Adding a new optional module is predictable and structured:

1. **Define module case in `App\Enums\Module`**:
   Add a new enum case, identifier, `label()`, `description()`, and `chiselTag()`.
2. **Declare module dependencies**:
   Specify any prerequisite modules in `Module::dependencies()`.
3. **Map owned files**:
   Add all module-exclusive files (actions, controllers, models, queries, requests, pages, tests) to `Module::ownedFiles()`.
4. **Map shared files**:
   Add any core/shared files containing chisel section markers (`/* @chisel-[tag] */`) to `Module::sharedFiles()`.
5. **Declare Composer packages**:
   Specify packages in `Module::composerPackages()`. Packages used across multiple modules (e.g., `spatie/laravel-data`) will only be pruned when all consuming modules are removed.
6. **Declare NPM packages**:
   Specify frontend packages in `Module::npmPackages()`.
7. **Declare permissions**:
   Add module permissions to `App\Enums\Permission` wrapped in chisel markers, and register them in `Module::permissions()`.
8. **Register routes**:
   Add route definitions in `routes/web.php` wrapped in chisel markers, and document them in `Module::routes()`.
9. **Add translations**:
   Create dedicated translation files in `lang/{en,fr,ar}/[module].php`.
10. **Implement frontend code**:
    Place components and pages under `resources/js/` and export types in `resources/js/types/index.ts` with chisel markers.
11. **Create migrations**:
    Provide isolated migrations with clear ownership (e.g. `create_[module]_table.php`).
12. **Configure Chisel transformations**:
    `chisel.php` automatically discovers the new module options through `Module::options()`.
13. **Add installation & removal tests**:
    Ensure the module is covered in `tests/Unit/Modules/ModuleTest.php` and `ModuleResolverTest.php`.
14. **Verify static analysis & type safety**:
    Run `vendor/bin/phpstan analyse` (level `max`) and `bun run test:types`.
15. **Update documentation**:
    Add the module to the compatibility matrix and roadmap.
16. **Format code**:
    Run `vendor/bin/pint --format agent` and `bun run lint`.

---

## 🧪 Testing & Quality Control

```bash
# Run full test suite with 100% code coverage requirement
composer test

# Run all unit and feature tests
vendor/bin/pest tests/Unit tests/Feature --compact

# Check type coverage (100% required)
vendor/bin/pest --type-coverage --min=100

# Static analysis (PHPStan at max level)
vendor/bin/phpstan analyse

# Code formatting & styling
composer run lint
```

---

## 📁 Key Directory Structure

```text
├── app/
│   ├── Actions/                  # Reusable business logic actions
│   ├── Console/Commands/         # Artisan commands
│   │   ├── InstallFeaturesCommand.php
│   │   ├── SetupAuthorizationCommand.php
│   │   └── SetupAdminUserCommand.php
│   ├── Data/                     # Spatie Data transfer objects
│   │   └── Reporting/            # Report summary, series, and breakdown DTOs
│   ├── Enums/                    # PHP string-backed enums
│   │   ├── AuditEvent.php
│   │   ├── Permission.php
│   │   ├── ReportCategory.php
│   │   ├── ReportType.php
│   │   └── Role.php
│   ├── Http/
│   │   ├── Controllers/          # Inertia HTTP controllers
│   │   │   ├── AuditTrails/
│   │   │   ├── Reporting/        # Invokable reporting & export controllers
│   │   │   └── Users/
│   │   ├── Middleware/           # HandleInertiaRequests (shares auth data)
│   │   └── Requests/            # Form Requests with authorize() & validation
│   ├── Models/                   # Eloquent models (User, AuditTrail, Setting)
│   ├── Queries/                  # Read-only query & export services
│   │   ├── AuditTrails/
│   │   ├── Reporting/            # UserReportQuery, AuditReportQuery, CSV streams
│   │   └── Users/
│   └── Providers/                # AppServiceProvider (Gate::before)
├── chisel.php                    # Feature pruning configuration
├── config/
│   ├── fortify.php
│   └── permission.php            # Spatie Permission config
├── database/migrations/          # Users, Audit Trails, Settings, Permissions
├── lang/                         # Localized translations (en, fr, ar)
├── resources/js/
│   ├── components/
│   │   ├── can.tsx               # <Can> authorization component
│   │   └── reports/              # Summary cards, SVG charts, date range filters
│   ├── hooks/
│   │   └── use-authorization.ts  # useAuthorization() hook
│   ├── pages/
│   │   └── reports/              # Catalog index, Users report, Audit report
│   └── types/
│       ├── auth.ts               # Auth type with permissions/roles
│       └── reports.ts            # Report DTO & filter type definitions
└── tests/
    ├── Feature/
    │   ├── Authorization/        # RBAC + command tests
    │   └── Reporting/            # Report queries, controllers, exports, and pruning tests
    └── Unit/
        ├── Enums/                # Enum tests
        └── Reporting/            # Report type & category tests
```

---

## 📄 License

This starter kit is open-sourced software licensed under the [MIT license](LICENSE).
