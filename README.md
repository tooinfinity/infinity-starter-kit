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

## 🧹 Interactive Feature Pruning (`chisel.php`)

### How Feature Pruning Works

For every unselected feature:
1. **Config** — Disables feature flags or deletes configuration files.
2. **Routes** — Removes route definitions from `routes/web.php`.
3. **Models** — Strips unused traits and interfaces from `app/Models/User.php`.
4. **Controllers & Actions** — Deletes unnecessary controllers and actions.
5. **Frontend Pages** — Deletes unused Inertia React pages and navigation tabs.
6. **Tests** — Deletes matching Pest test files.

### Non-Interactive Installation

```bash
php artisan install:features --answers='{"auth_features":["registration","two-factor-authentication"],"authorization_features":["roles-permissions"]}'
```

---

## 🗺️ Module Roadmap

- [x] **Authentication** — Registration, Email Verification, 2FA, Profile, Password, Session management.
- [x] **Authorization & RBAC** — Spatie Roles & Permissions, PHP enums, Gate bypass, Form Request authorization, frontend hooks.
- [ ] **User Management** — Admin user directory, creation/edit modals, role assignment, user deactivation.
- [ ] **Settings** — Expanded user profile, security controls, and application configuration.
- [ ] **Notifications** — Database & mail notification center, user preference toggles.
- [ ] **Audit Trails** — Searchable activity log tracking changes, IP addresses, user agents, and timestamps.
- [ ] **Reporting & Analytics** — Dashboard metrics, date filtering, CSV exports, queued export jobs.
- [ ] **Localization** — Supported locales, locale switcher component, translated UI messages.

---

## 🧪 Testing & Quality Control

```bash
# Run all tests
composer test

# Run feature tests
vendor/bin/pest tests/Feature

# Run authorization tests
vendor/bin/pest tests/Feature/Authorization tests/Unit/Enums

# Code formatters and linters
composer run lint

# Type check (PHPStan & TypeScript)
composer test:types
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
│   ├── Enums/                    # PHP string-backed enums
│   │   ├── Permission.php
│   │   └── Role.php
│   ├── Http/
│   │   ├── Controllers/          # Inertia HTTP controllers
│   │   ├── Middleware/           # HandleInertiaRequests (shares auth data)
│   │   └── Requests/            # Form Requests with authorize()
│   ├── Models/                   # Eloquent models (User with HasRoles)
│   └── Providers/                # AppServiceProvider (Gate::before)
├── chisel.php                    # Feature pruning configuration
├── config/
│   ├── fortify.php
│   └── permission.php            # Spatie Permission config
├── database/migrations/          # Users + Spatie Permission tables
├── resources/js/
│   ├── components/
│   │   └── can.tsx               # <Can> authorization component
│   ├── hooks/
│   │   └── use-authorization.ts  # useAuthorization() hook
│   └── types/
│       └── auth.ts               # Auth type with permissions/roles
└── tests/
    ├── Feature/Authorization/    # RBAC + command tests
    └── Unit/Enums/               # Enum tests
```

---

## 📄 License

This starter kit is open-sourced software licensed under the [MIT license](LICENSE).
