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

=== .ai/app.queries rules ===

# App/Queries guidelines

- Read-only database operations with complex constraints, sorting, or eager loading belong in `app/Queries`.
- Name after the query intent with the `Query` suffix (e.g. `PostByReferenceQuery`, `UserByEmailQuery`).
- Queries are invokable classes with a single `__invoke()`, `get()`, or `paginate()` method.
- Return types must be explicit: `Builder`, `Collection`, `LengthAwarePaginator`, or `?Model`.
- Queries must never mutate state, fire mutation events, or perform database writes.

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

=== .ai/general rules ===

# General Guidelines

- Don't include any superfluous PHP Annotations, except ones that start with `@` for typing variables.
- All PHP files must begin with `declare(strict_types=1);`.
- Every time you create a new file, stage it immediately with `git add <path>`. Never create git commits directly; leave git committing to the user.
- Do not write browser tests (`tests/Browser`, Pest browser plugin, Playwright). Cover behavior with Feature and Unit tests only.

=== .ai/resources.js rules ===

# Frontend UI stability

Every UI change keeps the layout still. Nothing may flash, jump, clip or reflow on first load, navigation, polling, or after an action. Target is 0 layout shift (CLS = 0).

## Loading

- A skeleton occupies exactly the loaded content's box: same height, line count and wrapping at every breakpoint, including the narrow aside. It is visible in light and dark mode, popovers included.
- Delay skeletons by about 300ms so fast responses show nothing. Show one only while data is genuinely missing, never while refetching data you already have.
- Shared props that persist across pages (aside, nav counts) use `Inertia::defer(...)->once()`, so client visits don't refetch them and flash.
- Polling and refresh buttons use partial reloads (`only: [...]`) of the page's own props. Never reload the whole page, and keep showing the old data until the new data arrives.

## Changing content

- Numbers, counts and countdowns use `tabular-nums` in fixed-width slots.
- Buttons whose label changes ("Following", "Saving…") get a `min-w`. Disabled, loading and enabled states are the same size.
- Conditional UI (banners, "@x is reviewing", inline confirms, errors) reserves its space or overlays. An inline confirm fits in the slot of the control it replaces.
- Removed list rows collapse with animated transition, keeping scroll position and focus.
- Right after an action, the control under the cursor must not flip to a destructive state, e.g. Follow → "Unfollow" before the pointer leaves.

## Overflow

- Dialogs and popovers are capped to the viewport (`dvh`) and scroll inside, with a sticky header and footer when actions would scroll away. No double scrollbars and no page scroll through the overlay. Scroll lock must not shift the page: keep `scrollbar-gutter: stable`.
- Long usernames, URLs, reasons, queue names and messages use `min-w-0` with `truncate` or `line-clamp`, and the full text in `title`.
- Auto-growing textareas are `overflow-x-hidden`. Inputs use at least 16px text below `md` so iOS doesn't zoom.
- On mobile, toasts sit above the bottom nav.

## Verify before calling it done

- Check in a browser at 1440×900, 1366×650 and 390×844, in light and dark.
- Measure layout shift (`PerformanceObserver` with `layout-shift`) for first load, navigation, a polling cycle and every action you added. The target is 0.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v5
- phpunit/phpunit (PHPUNIT) - v13
- rector/rector (RECTOR) - v2

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from `@/actions` or `@/routes`, or running `wayfinder:generate`. Use Wayfinder route functions instead of hardcoded URLs.
- `fortify-development` — ACTIVATE when working on authentication in Laravel (login, registration, password reset, email verification, 2FA, profile updates).

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `bun run build` (or `npm run build`) or ask the user to run `bun run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes that depend on Laravel ecosystem APIs, behavior, configuration, or syntax. Skip it for copy-only edits and trivial changes.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries because package info is already shared.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

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

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.

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

=== laravel-octane/core rules ===

# Laravel Octane

This application uses Laravel Octane, a long-running PHP server. The application bootstraps once and handles many requests within the same process.

- Never store request-specific state in singletons or static properties, because it can leak across requests.
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`).
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services.

=== wayfinder/core rules ===

# Laravel Wayfinder

- Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).
- Never use hardcoded URLs or string concatenation for API or page endpoints in frontend components.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

## Testing Form Requests & Actions

- Test Form Request authorization:
  - Test that unauthorized attempts return 403 or 404 (when using `denyAsNotFound()`).
  - Test validation rules with invalid inputs using `$response->assertSessionHasErrors(...)` or `$response->assertJsonValidationErrors(...)`.
- Test actions through feature tests exercising the full HTTP boundary or directly via unit tests using factories.

=== inertia-react/core rules ===

# Inertia + React

- Activate `inertia-react-development` when working with Inertia React client-side patterns.
- Always use `@inertiajs/react` components (`Link`, `useForm`, `usePage`) with Wayfinder route helpers.

=== chisel/core rules ===

# Chisel Feature Architecture & Engineering Conventions

- Always inspect existing implementation before modifying architecture.
- Preserve established conventions and architectural boundaries.
- Keep Actions, controllers, and models strictly consistent with existing repository rules (Actions perform mutations with single `handle()`, controllers are CRUD-only, Form Requests handle 100% of authorization and validation).
- Do not introduce unrelated refactors or clean up unrelated code.
- Do not weaken static analysis or tests; never disable lint rules to make changes pass.
- Preserve public APIs unless explicitly directed to change them.
- Add regression tests for all bugs and architectural enhancements.
- Chisel-removable features must leave a clean, valid generated application with zero orphaned markers (`assertNoOrphanedMarkersInSandbox`) and valid PHP syntax (`assertPhpSyntaxValid`).
- Use the existing feature/marker/dependency infrastructure (`FeatureDefinition`, `FeatureRegistry`, `DependencyValidator`) rather than creating parallel or competing systems.
- Always run repository-defined verification commands (`composer test:lint`, `composer test:types`, `composer test`).

## Registrable Resource Convention

Future features integrating polymorphic or registrable entity models must follow this convention:
- Resource types must use stable, explicit string keys (e.g., `'organization'`, `'location'`, `'user'`).
- Arbitrary PHP model class names must never come directly from user input or request payloads.
- Supported resources must be explicitly allow-listed/registered in a centralized configuration or registry.
- Future features should reuse an established registry rather than creating competing ad-hoc registries.
- Generalize the implementation only after multiple concrete consumers prove the abstraction.

</laravel-boost-guidelines>

