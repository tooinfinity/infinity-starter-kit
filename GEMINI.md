<laravel-boost-guidelines>
=== .ai/app.actions rules ===

# App/Actions guidelines

- This application uses the Action pattern and prefers for much logic to live in reusable and composable Action classes.
- Actions live in `app/Actions`, they are named based on what they do, with no suffix.
- Actions will be called from many different places: jobs, commands, HTTP requests, API requests, MCP requests, and more.
- Create dedicated Action classes for business logic with a single `handle()` method.
- Inject dependencies via constructor using private properties. If an action has no dependencies, omit `__construct`.
- Create new actions with `php artisan make:action "{name}" --no-interaction`.
- Wrap complex operations in `DB::transaction()` within actions when multiple models are involved.
- Actions never authorize or validate anything: that lives strictly in the form request of the endpoint calling them.
- Return values: Return the affected `Model`, `Collection`, or `void`. Never return HTTP responses (`RedirectResponse`, `JsonResponse`).

## CRUD by design

- Every action name starts with `Create`, `Update`, or `Delete`. Never with any other verb: no `Approve`, `Reject`, `Pin`, `Unpin`, `Star`, `Follow`, `Save`, `Sync`, `Notify`, `Render`, `Fetch`, `Mark`, `Export`, `Store`, `Process`.
- Name the rest after the model, relationship, or attribute being changed, and pass what it changes to as an argument, so one action covers every value:
  - `ApprovePost` / `RejectPost` → `UpdatePostStatus::handle($post, PostStatus::Approved)`.
  - `ApprovePosts` → `UpdatePostStatuses::handle($postIds, PostStatus::Approved)`.
  - `PinPost` / `UnpinPost` → `UpdatePostPin` / `DeletePostPin`.
  - `StarPost` / `UnstarPost` → `CreatePostStar` / `DeletePostStar`.
  - `FollowUser` / `UnfollowUser` → `CreateUserFollow` / `DeleteUserFollow`.
  - `ExportPersonalData` → `CreateExport`.
  - `NotifyAboutReplies` → `CreateReplyNotifications`.
- Mirror the controller and form request it serves: `PostStarController@store` → `CreatePostStarRequest` → `CreatePostStar`.
- Code that only reads is not an action: database reads go in `app/Queries` (`PostByReferenceQuery`), and pure parsing or computing goes in `app/Services` (`Urls`, `Mentions`).
- `tests/Unit/ArchTest.php` enforces this.

<!-- Example action class -->
```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePostStatus
{
    public function __construct(
        private UpdatePostStatuses $updatePostStatuses,
    ) {}

    public function handle(Post $post, PostStatus $status, ?string $reason = null): Post
    {
        return DB::transaction(function () use ($post, $status, $reason) {
            $this->updatePostStatuses->handle([$post->id], $status, $reason);

            return $post->refresh();
        });
    }
}
```

=== .ai/app.chisel rules ===

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
- Always run `composer test:installer` when modifying installer-related logic or feature paths to ensure full test suite coverage for pruning flows.

=== .ai/app.http.controllers rules ===

# App/Http/Controllers guidelines

- Controllers are CRUD by design: their only public methods are `index`, `create`, `store`, `show`, `edit`, `update`, and `destroy`.
- Never add a custom method (`approve`, `pin`, `follow`, `export`, `markAsRead`). Instead, find the resource being created, updated, or deleted, and give it its own controller:
  - Approving a post → `PostApprovalController@store`. Rejecting it → `PostRejectionController@store`.
  - Pinning a post → `PostPinController@update`. Unpinning it → `PostPinController@destroy`.
  - Following a user → `UserFollowController@store`. Unfollowing → `UserFollowController@destroy`.
  - Approving items in a moderation queue → `ModerationController@update`.
- When an endpoint really does not fit CRUD, that is the sign to use an invokable controller with a single `__invoke()` method, named after the thing, minimally: `ExportController`, not `PersonalDataExportController` or `ExportPersonalDataController`.
- Controller names are nouns, never verbs: `PostStarController`, not `StarPostController`.
- Controllers stay thin: resolve the form request, call one action (or query), and return a response. Maximum 10–15 lines per action method.
- Controllers never authorize or validate anything: no `abort()`, `abort_if()`, `abort_unless()`, `throw_if()`, `throw_unless()`, `$request->validate()`, `Validator::make()`, or `ValidationException`. Every check lives in the form request of that endpoint.
- Extract validated input exclusively via `$request->validated()` or typed helper methods on the form request. Never use `$request->all()`.
- Use the `#[CurrentUser]` attribute to inject the authenticated user into controller methods.
- `tests/Unit/ArchTest.php` enforces this.

<!-- Example controller -->
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePostStar;
use App\Actions\DeletePostStar;
use App\Http\Requests\CreatePostStarRequest;
use App\Http\Requests\DeletePostStarRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class PostStarController
{
    public function store(
        CreatePostStarRequest $request,
        #[CurrentUser] User $user,
        Post $post,
        CreatePostStar $action,
    ): RedirectResponse {
        $action->handle($user, $post);

        return back();
    }

    public function destroy(
        DeletePostStarRequest $request,
        #[CurrentUser] User $user,
        Post $post,
        DeletePostStar $action,
    ): RedirectResponse {
        $action->handle($user, $post);

        return back();
    }
}
```

=== .ai/app.http.requests rules ===

# App/Http/Requests guidelines

- Never use policies or gates: no `app/Policies`, no `Gate::define()`, no `can:` middleware, no `$user->can()`, no `$this->authorize()`.
- Authorization and validation only ever happen in form requests: authorization in `authorize()`, validation in `rules()`. Never in controllers, actions, or models. This includes `index` and `show` endpoints.
- Controllers and actions never check anything themselves: no `abort()`, `abort_if()`, `abort_unless()`, `throw_if()`, `throw_unless()`, `Validator::make()`, `$request->validate()`, or `ValidationException`.
- Create a dedicated form request for each endpoint that checks anything, and never share one between endpoints. If the endpoint has none yet, create one.
- Name it after the controller method it serves, CRUD by design:
  - `index` → `List…Request`
  - `show` → `Show…Request`
  - `store` → `Create…Request`
  - `update` → `Update…Request`
  - `destroy` → `Delete…Request`
  - Invokable → `{Name}Request` (e.g. `ExportRequest`)
  - Examples: `PostPinController@update` → `UpdatePostPinRequest`, `PostPinController@destroy` → `DeletePostPinRequest`, `PostApprovalController@store` → `CreatePostApprovalRequest`.
- When hiding something should look like it does not exist, return `Response::denyAsNotFound()` from `authorize()` instead of `false`.
- Type assertion for static analysis: Always assert resolved models and users inside `authorize()` (e.g. `assert($user instanceof User); assert($post instanceof Post);`).
- Put input interpretation in form request methods (`$request->tab()`, `$request->targetStatus()`), and complex checks in `App\Rules` validation rules.
- `tests/Unit/ArchTest.php` enforces this.

<!-- Example form request -->
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookmarkRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $post = $this->route('post');
        assert($post instanceof Post);

        return $post->isVisibleTo($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
```

=== .ai/app.models rules ===

# App/Models guidelines

- Models are schemas, relationships, query scopes, and lightweight predicates only. Never place workflow orchestration or multi-model mutation logic inside Models.
- Always use the modern `protected function casts(): array` method instead of the legacy `$casts` property.
- Keep strict N+1 lazy loading prevention enabled. Always eager-load relations needed by frontend views.
- Control model serialization: declare what leaves the server using `#[Visible]` with an explicit allowlist or `#[Hidden]` covering sensitive columns.
- Keep business mutations in Actions (`app/Actions`) and complex read queries in Queries (`app/Queries`).

=== .ai/app.services rules ===

# App/Services guidelines

- Every third-party service the app reaches over the network (an AI provider, a mailer, a payment or streaming platform) sits behind a contract, so swapping vendor changes one class.
- The contract is an interface in `app/Contracts/Services`, named after the role it plays, never the vendor: `AnalyticsProvider`, `TurnProvider`.
- Each implementation lives in `app/Services/{Role}s/` and is named vendor + role: `app/Services/AnalyticsProviders/FathomAnalyticsProvider.php`.
- Add the contract to service provider bindings: `PRODUCTION_SERVICES` with the real implementation, and `LOCAL_SERVICES` with a local one that needs no account, credentials or network (`Local{Role}` or `Null{Role}`).
- Everything else depends on the contract, never on the implementation: inject it into constructors, controller methods, or form requests.
- Classes in `app/Services` that only parse or compute (`Urls`, `Mentions`) talk to nobody and stay plain classes without an interface contract.

<!-- Example service contract and implementation -->
```php
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface AnalyticsProvider
{
    /**
     * @param array<string, mixed> $data
     */
    public function track(string $event, array $data = []): void;
}

namespace App\Services\AnalyticsProviders;

use App\Contracts\Services\AnalyticsProvider;

final readonly class FathomAnalyticsProvider implements AnalyticsProvider
{
    public function track(string $event, array $data = []): void
    {
        // Implementation
    }
}
```

=== .ai/data rules ===

# Data guidelines

Assume the database has already been leaked. Every column, row, cache entry, log line, and file we keep is something an attacker already has. The safest data is the data we never stored.

## Store as little as possible

- Do not add a column, table, cache key, or file unless the feature cannot work without it. "Might be useful later", analytics, debugging, and convenience are not reasons to store anything.
- Derive instead of storing: counts, flags, and anything computable from data we already hold are queried or computed, never persisted. Caching a computation is fine when the cache entry has a TTL and holds nothing sensitive.
- Keep only what is shown. If a value is fetched from a third party and never displayed or acted on, drop it on the spot instead of saving it "just in case".
- Prefer short-lived over permanent. Typing indicators, signature nonces, and sync markers live in the cache with a TTL, not in a table.
- Every new column needs three answers in the migration's pull request: why the feature cannot work without it, who is allowed to see it, and when it is deleted. If any answer is "not sure", do not add the column.

## Name timestamps after the moment

- Every date or time column ends in `_at` and names the moment it records: `created_at`, `updated_at`, `verified_at`, `expires_at`. Never `_since`, `_on`, `_date`, `_time`, or a bare noun, so every timestamp reads the same way across features.

## Never store secrets

- Never persist tokens, secrets, passwords, private keys, OAuth access or refresh tokens, API keys, session ids, raw signatures, or anything that grants access.
- Credentials the app needs come from the environment (`config/services.php`), never from a table, a seeder, a test fixture, or a committed file.
- Short-lived secrets that must be held between requests go in the cache, encrypted with `Crypt::encryptString()`, with a TTL shorter than their lifetime.
- Never log or report a secret, a request body, a message body, an email address, or a full request. Log identifiers (user id, status code) and nothing else.
- Never put a secret in a job payload, a queued notification, a flash message, a URL, a cookie, or an Inertia prop.

## Encrypt sensitive content

- Use encrypted Eloquent casts (`'encrypted'`) for stored content that contains or can reasonably contain sensitive information: private messages, personal notes, non-public personal or contact details.
- Ordinary operational metadata—timestamps, booleans, counters, foreign keys, status enums—uses normal column types and casts.
- Encrypted content must not have an unencrypted duplicate for searching or indexing.

## Expose nothing by default

- Every model that reaches the frontend or API declares what leaves the server: `#[Visible([...])]` with an explicit allowlist, or `#[Hidden([...])]` covering every column that is not public. New columns are hidden until proven necessary.
- Internal storage paths and third-party secret identifiers are never serialized. When a model needs a URL, expose a named route, not the storage path.
- Only the user themselves sees their own private settings and hidden fields. Error and validation messages must not reveal another user's hidden state either.

## Delete everything with the account

- Any new table, column, or stored file that relates to a user must cascade on delete or be removed explicitly when a user account is deleted, with test coverage ensuring clean removal.

=== .ai/general rules ===

# General Guidelines

- Don't include any superfluous PHP Annotations, except ones that start with `@` for typing variables.
- All PHP files must begin with `declare(strict_types=1);`.
- Every time you create a new file, stage it immediately with `git add <path>`. Never commit; leave committing to the user.
- Do not write browser tests (`tests/Browser`, Pest browser plugin, Playwright) unless explicitly requested. Cover behavior with Feature and Unit tests primarily.
- Migrations only go up: never write a `down` method. To undo a migration, write a new one.

## Commit attribution

- Never add Amp, Claude Code, or any other AI tool as a co-author: no `Co-authored-by` trailers naming them, such as `Co-authored-by: Amp <amp@ampcode.com>` or `Co-authored-by: Claude <noreply@anthropic.com>`.
- Never put their names anywhere in commit attribution: not as the commit author or committer, and not in trailers or footers such as `Amp-Thread-ID` or "Generated with Claude Code". Commits are attributed to the human contributors only.

## Minimal code

- Write the least code that does the job: no private helpers, extra cache layers, or defensive checks the feature doesn't need. If a method can be inlined or a branch removed without losing behavior, do it.
- Prefer Laravel collections and the framework's typed accessors over hand-written array handling: `collect(...)->map(...)` instead of building arrays in loops, `$response->collect()` / `$response->fluent()` with `->integer()` / `->string()` instead of `is_array` / `is_numeric` checks and `(int)` casts.
- For very large or repeated array shapes, define a named `@phpstan-type` alias on the class that owns the data and reuse it in PHPDoc annotations. Use `@phpstan-import-type` in other classes instead of duplicating the shape; keep small, one-off shapes inline.

## Required verification

- After changes, before reporting completion or pushing, run `composer test:lint`, `composer test:types`, `composer test:type-coverage`, and `bun run test:unit`.
- Run the full test suite or relevant unit/feature suites: `composer test:unit` and `composer test:database`.
- When modifying features affected by the feature installer, run `composer test:installer`.
- Fix failures introduced by the changes. Never lower coverage thresholds or exclude code just to pass. Report pre-existing failures or environment blockers explicitly; do not claim a check passed when it failed or could not run.

## Open source

- This repository is public. Never write anything private into code, comments, rules, tests, commit messages, or pull requests: secrets, credentials, personal data, private user content, or internal production details.

=== .ai/github.pull-requests rules ===

# Pull request guidelines

Apply these rules when an agent or developer prepares, opens, or updates a pull request.

## Choose the template

- Use `.github/PULL_REQUEST_TEMPLATE/without-media.md` for backend, refactoring, and text-only PRs.
- Use `.github/PULL_REQUEST_TEMPLATE/with-media.md` for PRs involving visible frontend UI changes with screenshots or recordings.

## Prepare and implement

1. Confirm the task, repository guidelines, and intended base and head branches.
2. Isolate work in a branch or worktree, keeping unrelated changes clean.
3. Follow the repository's architecture, staging, and verification rules.
4. Run all required checks: `composer test:lint`, `composer test:types`, `composer test:type-coverage`, `composer test:unit`, and `composer test:installer` (if feature-related).
5. For user-visible changes, follow frontend stability rules (CLS = 0) and verify across multiple viewports and themes.

## Title and conventional commits

- Pull request titles must strictly follow the Conventional Commits specification: `type(scope): description`.
- Valid types include `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`.
- Enforced automatically by `.github/workflows/lint-pr-title.yml`.

## Independent review before publication

1. Remove temporary debug instrumentation and test artifacts.
2. Verify all local checks pass without warnings or regressions.
3. Fill out the selected PR template cleanly, removing HTML comments before opening the PR.

=== .ai/github.workflows rules ===

# GitHub workflows guidelines

Workflows in this repository only ever read the code and report whether it passes. They never write to the repository, GitHub, or production. A workflow that can write is a workflow an attacker can use through a dependency, fork, or pull request.

## Read-only, always

- Every workflow declares exactly this at the top level, and nothing else:

<!-- Workflow permissions -->
```yaml
permissions:
  contents: read
```

- No job or step raises permissions. Never `contents: write`, `pull-requests: write`, `issues: write`, `packages: write`, `actions: write`, `deployments: write`, `id-token: write`, `security-events: write`, or `permissions: write-all`. If a feature needs one of these, it does not run in GitHub Actions.
- Workflows never push commits or tags, create releases, comment on pull requests, add labels, or deploy. Deploys happen from the hosting platform, never from a workflow.
- The only output a workflow may produce is a build artifact uploaded with `actions/upload-artifact` and the job status itself.

## No secrets

- Workflows and composite actions in `.github/` never use `secrets.*`, never pass `GITHUB_TOKEN` to a step, an `env:`, or an action input, and never contain deploy keys, SSH keys, API keys, or credentials of any kind.
- Repository secrets stay empty.

## Safe triggers and inputs

- Only `push`, `pull_request`, `schedule`, and `workflow_dispatch` trigger workflows. Never `pull_request_target`, `workflow_run`, `issue_comment`, or `repository_dispatch`.
- Never interpolate user-controlled values (such as `github.event.pull_request.title`) directly into `run:`. Pass them through `env:` and read them as shell variables.
- Jobs run on GitHub-hosted `ubuntu-latest` runners only, with a defined `timeout-minutes`.

## Trusted actions only

- Third-party actions are pinned to a full commit SHA with the version in a comment, and Dependabot keeps them current.
- Use trusted composite actions (`./.github/actions/setup`, `./.github/actions/setup-php`, `./.github/actions/setup-frontend`) to avoid duplicating dependency and caching configurations.
- `actions/checkout` runs with `persist-credentials: false`.
- Dependencies are installed from lockfiles only (`composer install`, `bun install --frozen-lockfile`).

=== .ai/resources.js rules ===

# Frontend UI stability

Every UI change keeps the layout still. Nothing may flash, jump, clip or reflow on first load, navigation, polling, or after an action. Target is 0 layout shift (CLS = 0).

## Loading

- A skeleton occupies exactly the loaded content's box: same height, line count and wrapping at every breakpoint, including the aside. It is visible in light and dark mode, popovers included.
- Delay skeletons by about 300ms so fast responses show nothing. Show one only while data is genuinely missing, never while refetching data you already have.
- Shared props that persist across pages (nav items, user context) use `Inertia::defer(...)->once()`, so client visits don't refetch them and flash.
- Polling and refresh buttons use partial reloads (`only: [...]`) of the page's own props. Never reload the whole page, and keep showing the old data until the new data arrives.

## Changing content

- Numbers, counts and countdowns use `tabular-nums` in fixed-width slots.
- Buttons whose label changes ("Saving…", "Confirming…") get a `min-w`. Disabled, loading and enabled states are the same size.
- Conditional UI (banners, inline confirms, errors) reserves its space or overlays. An inline confirm fits in the slot of the control it replaces.
- Removed list rows collapse with animated transition, keeping scroll position and focus.
- Right after an action, the control under the cursor must not flip to an unexpected destructive state.

## Routing and Wayfinder

- Always use Laravel Wayfinder typed route functions imported from `@/actions/` or `@/routes/`.
- Never use hardcoded URL strings or string concatenation for backend endpoints.

## Overflow & Responsive Viewports

- Dialogs and popovers are capped to the viewport (`dvh`) and scroll inside, with a sticky header and footer when actions would scroll away. Scroll lock must keep `scrollbar-gutter: stable`.
- Long usernames, URLs, and labels use `min-w-0` with `truncate` or `line-clamp`, with the full text in `title`.
- Auto-growing textareas are `overflow-x-hidden`. Inputs use at least 16px text below `md` so iOS doesn't zoom.

## Verification

- Check in a browser at 1440×900, 1366×650 and 390×844, in both light and dark modes.
- Measure layout shift (`PerformanceObserver` with `layout-shift`) for first load, navigation, and actions. Target is 0.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `bun run build` or ask the user to run `bun run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== inertia-svelte/core rules ===

# Inertia + Svelte

- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

