# Performance Budgets for Generated Applications

This document establishes and describes the authoritative, reproducible performance budgets for applications generated from **Infinity Starter Kit**.

## Purpose & Philosophy

As optional modules (such as authorization, settings, notifications, reporting, and localization) and shared application infrastructure grow, they risk introducing unintended request work, middleware-induced queries, N+1 lookups, or frontend bundle bloat.

The performance budget suite exists to:
- Detect regressions early in automated CI before they reach production.
- Focus on **deterministic, hard metrics** (database query counts and production asset bundle bytes).
- **Exclude flaky wall-clock / timing-based thresholds** on shared, variable CI runners.
- Enforce explicit, committed rationale whenever budgets need to change.
- Complement functional correctness tests without replacing them.

---

## Canonical Requests & Query Count Baselines

Every web request in a generated application passes through the middleware stack and shared Inertia state. We measure a canonical set of requests representing typical entry points:

1. **`GET /` (Welcome Page)**: Public guest entry point.
2. **`GET /login` (Inertia Login Page)**: Public authentication endpoint with Inertia headers.
3. **`GET /dashboard` (Authenticated Dashboard)**: Core authenticated area resolving user, session, roles, permissions, and notifications.

### Query Count Budgets per Profile

| Profile | `GET /` (Guest) | `GET /login` (Inertia) | `GET /dashboard` (Auth) | Rationale & Architectural Overhead |
| :--- | :---: | :---: | :---: | :--- |
| **`minimal`** | **0** | **0** | **0** | Settings, RBAC, and Notifications are pruned. Zero database queries are executed for canonical requests. |
| **`default`** (Standard) | **1** | **1** | **5** | Uncached `HandleSettings` middleware executes at most 1 query on guest endpoints. On `/dashboard`, up to 5 queries on an isolated request: uncached settings (1) + Spatie direct permissions (1) + Spatie roles (1) + Spatie role permissions (1) + unread notifications count (1). |
| **`full`** | **1** | **1** | **5** | Contains all optional modules. Canonical requests share the same bounded query overhead as standard. |

> [!NOTE]
> Query counts are measured with `session.driver = 'array'` and `cache.default = 'array'` so that measurements reflect application architecture (queries in middleware, queries in models, queries in Inertia `share()`) rather than driver-level session persistence writes.

---

## Frontend Production Bundle Budgets

Frontend production assets are built using Vite+ / Rolldown with Tailwind CSS v4 and React 19.
Budgets are enforced against the built assets in `public/build` derived from `public/build/manifest.json`.

Budgets are chosen with intentional headroom (~10-15%) above current production baselines to absorb normal UI refinement while immediately halting large regressions (e.g. bundling unpruned packages, loss of route-level code splitting, or unintended asset inclusion).

### Production Asset Budgets (Uncompressed Bytes)

| Metric | Measured Baseline (`full`) | Budget (`minimal`) | Budget (`default`) | Budget (`full`) | Description |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **`entry_js`** (`app.tsx`) | ~84,679 B (~85 KB) | **95,000 B** (~95 KB) | **95,000 B** (~95 KB) | **95,000 B** (~95 KB) | Main application JavaScript entry chunk. |
| **`entry_css`** (`app.css`) | ~124,762 B (~125 KB) | **135,000 B** (~135 KB) | **135,000 B** (~135 KB) | **135,000 B** (~135 KB) | Compiled Tailwind CSS stylesheet. |
| **`total_js`** (all chunks) | ~857,421 B (~857 KB) | **650,000 B** (~650 KB) | **850,000 B** (~850 KB) | **950,000 B** (~950 KB) | Sum of all code-split JavaScript chunks and page components. |
| **`total_css`** (all stylesheets) | ~127,114 B (~127 KB) | **140,000 B** (~140 KB) | **140,000 B** (~140 KB) | **140,000 B** (~140 KB) | Sum of all compiled CSS stylesheets (including fonts). |

---

## Enforcement & Verification

Performance budgets are verified automatically in multiple layers:

### 1. Unit & Integrity Tests

- Class: `App\Chisel\Installer\PerformanceBudget`
- Unit Test: `tests/Unit/Chisel/PerformanceBudgetTest.php`
- Verifies budget specifications, canonical requests, metric definitions, threshold assertions, manifest calculation, and query script generation with 100% test coverage.

### 2. Generated Application Smoke Pipeline

- Pipeline: `Tests\Support\GeneratedApplicationSmokePipeline::validatePerformanceBudgets`
- Smoke Test: `tests/Feature/Chisel/GeneratedApplicationSmokeTest.php`
- Run via:
  ```bash
  composer test:smoke -- --filter="minimal"
  composer test:smoke -- --filter="default"
  composer test:smoke -- --filter="full"
  ```
- Generates a real, isolated application, prunes features according to the specified profile, migrates the database, builds production assets, and asserts:
  1. Frontend bundle sizes remain strictly within budget.
  2. Canonical request query counts remain within budget.

### 3. Dedicated Performance Test Suite

- Feature Test: `tests/Feature/Chisel/GeneratedApplicationPerformanceTest.php`
- Verifies the root application's frontend production bundle and canonical request query counts against full-profile budgets, including intentional regression detection. (Representative generated profiles `minimal`, `default`, and `full` are validated end-to-end by the smoke pipeline).

---

## Policy for Updating Budgets

Budgets are deliberately locked and version-controlled. If a feature or dependency genuinely requires modifying a budget:

1. **Explicit Rationale**: The pull request and commit message must state why the budget increased (e.g. "feat: add charting library adding +30KB to bundle").
2. **Deterministic Metric**: Do not introduce timing or latency thresholds. Only deterministic query counts or byte ceilings are permitted.
3. **Synchronized Documentation**: Update both `app/Chisel/Installer/PerformanceBudget.php` and this document (`docs/performance-budgets.md`).
