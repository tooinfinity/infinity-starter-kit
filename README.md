# Infinity Starter Kit (Laravel + Inertia + React)

A full-featured, modular Laravel starter kit powered by **[Laravel Chisel](https://github.com/laravel/chisel)** and **[Laravel Fortify](https://laravel.com/docs/fortify)**. 

Designed for speed and cleanliness: choose your features during `composer create-project`, and Chisel automatically prunes unused backend routes, controllers, actions, Inertia pages, traits, model interfaces, and Pest tests.

---

## ⚡ Tech Stack

- **Framework**: [Laravel 13](https://laravel.com) (PHP 8.5+)
- **Frontend SPA**: [Inertia.js v3](https://inertiajs.com) + [React 19](https://react.dev)
- **TypeScript & Routing**: [Laravel Wayfinder](https://github.com/laravel/wayfinder) (`@/actions`, `@/routes`)
- **Styling**: [Tailwind CSS v4](https://tailwindcss.com) + Radix UI primitives + Lucide Icons
- **Bundler**: [Vite-Plus](https://vite.dev) / Bun
- **Authentication**: [Laravel Fortify](https://laravel.com/docs/fortify)
- **Feature Pruning**: [Laravel Chisel](https://github.com/laravel/chisel)
- **Testing**: [Pest 5](https://pestphp.com)

---

## 🚀 Quick Start

### 1. Create a New Project

Create your project via Composer:

```bash
composer create-project tooinfinity/infinity-starter-kit my-app
```

During setup, the `post-create-project-cmd` hook will automatically:
1. Generate your application encryption key (`key:generate`).
2. Initialize your local database (`database/database.sqlite`).
3. Run database migrations (`migrate`).
4. Trigger the interactive **`php artisan install:features`** command powered by **Chisel**.

### 2. Select Your Features

When prompted:

```text
Which authentication features would you like to enable?
 [x] Registration
 [x] Email verification
 [x] Two-factor authentication
```

Select the features you want using `Space`, then press `Enter`. Chisel will instantly trim the codebase—removing unselected routes, controllers, views, traits, interfaces, and test files across the entire vertical slice.

### 3. Start Development

Navigate to your project directory and start the development server:

```bash
cd my-app
bun run dev
```

---

## 🛠️ Implemented Modules

### 🔐 Authentication Module

The starter kit features a complete, production-ready Fortify backend and React frontend with granular Chisel feature pruning:

| Feature | Description | Chisel Pruning Behavior |
| :--- | :--- | :--- |
| **Registration** | User registration form, routes, and user creation action. | When disabled, removes `/register` route, `CreateUser` action, registration page, and login page register links. |
| **Email Verification** | Native Fortify verification flow (`MustVerifyEmail`), verification notice page, resend notifications, and signed link verification. | When disabled, strips `MustVerifyEmail` interface from `User` model, removes verification middleware from routes, and deletes verification controllers, actions, views, and Pest tests. |
| **Two-Factor Authentication (2FA)** | TOTP / QR codes, recovery codes, security settings page, and 2FA challenge login flow. | When disabled, strips `TwoFactorAuthenticatable` trait from `User` model, removes 2FA routes, settings UI tab, challenge flow pages, controllers, and Pest tests. |
| **Account & Security** | User login/logout, password reset flow, profile updates, password change, and appearance settings. | **Core Always Retained** — Essential session, profile, and security controls remain intact. |

---

## 🧹 Interactive Feature Pruning (`chisel.php`)

Chisel ensures your new application stays clean without leaving dead code or disabled endpoints.

### How Feature Pruning Works

For every unselected feature in `chisel.php`:
1. **Config**: Disables feature flags in `config/fortify.php`.
2. **Routes**: Removes route definitions from `routes/web.php`.
3. **Models**: Strips unused traits (e.g. `TwoFactorAuthenticatable`) and interfaces (e.g. `MustVerifyEmail`) from `app/Models/User.php`.
4. **Controllers & Actions**: Deletes unnecessary controllers and actions.
5. **Frontend Pages**: Deletes unused Inertia React pages and navigation tabs.
6. **Tests**: Deletes matching Pest test files, keeping your test suite 100% green.

### Running Non-Interactive Installation

For automated boilerplate generation or CI pipelines, pass pre-selected answers as JSON:

```bash
php artisan install:features --answers='{"auth_features":["registration","two-factor-authentication"]}'
```

---

## 🗺️ Module Roadmap

The Infinity Starter Kit is designed as a comprehensive foundation. Upcoming modules will be introduced as vertical slices:

- [x] **Authentication**: Registration, Email Verification, 2FA, Profile, Password, Session management.
- [ ] **Authorization & RBAC**: Laravel policies/gates, optional Spatie Permission integration for roles & permissions.
- [ ] **User Management**: Admin user directory, creation/edit modals, role assignment, user deactivation.
- [ ] **Settings**: Expanded user profile, security controls, and application configuration.
- [ ] **Notifications**: Database & mail notification center, user preference toggles.
- [ ] **Audit Trails**: Searchable activity log tracking changes, IP addresses, user agents, and timestamps.
- [ ] **Reporting & Analytics**: Dashboard metrics, date filtering, CSV exports, queued export jobs.
- [ ] **Localization**: Supported locales, locale switcher component, translated UI messages.

---

## 🧪 Testing & Quality Control

This project adheres to strict code quality standards and 100% test coverage using Pest 5.

```bash
# Run all tests (unit & feature)
composer test

# Run feature tests specifically
vendor/bin/pest tests/Feature

# Run code formatters and linters (Pint, Rector, ESLint)
composer run lint

# Run type check (PHPStan & TypeScript)
composer test:types
```

---

## 📁 Key Directory Structure

```text
├── app/
│   ├── Actions/               # Reusable single-purpose business logic actions
│   ├── Console/Commands/      # Artisan commands (including install:features)
│   ├── Http/Controllers/      # Inertia HTTP controllers
│   ├── Models/                # Eloquent models (User, etc.)
│   └── Providers/             # Fortify & App service providers
├── chisel.php                 # Chisel feature pruning configuration
├── config/                    # Application configuration (fortify.php, etc.)
├── resources/
│   └── js/
│       ├── components/        # Shared React UI components (Radix UI)
│       ├── layouts/           # Page layouts (App, Settings, Auth)
│       └── pages/             # Inertia React page components
├── routes/
│   └── web.php                # Web routes with Chisel markers
└── tests/
    └── Feature/               # Pest feature tests for all modules
```

---

## 📄 License

This starter kit is open-sourced software licensed under the [MIT license](LICENSE).
