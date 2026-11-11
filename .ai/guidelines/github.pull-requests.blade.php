# Pull request guidelines

Apply these rules when an agent or developer prepares, opens, or updates a pull request.

## Choose the template

- Use `.github/PULL_REQUEST_TEMPLATE/without-media.md` for backend, refactoring, and text-only PRs.
- Use `.github/PULL_REQUEST_TEMPLATE/with-media.md` for PRs involving visible frontend UI changes with screenshots or recordings.

## Prepare and implement

1. Confirm the task, repository guidelines, and intended base and head branches.
2. Isolate work in a branch or worktree, keeping unrelated changes clean.
3. Follow the repository's architecture, staging, and verification rules.
@if (file_exists(base_path('chisel.php')))
4. Run all required checks: `composer test:lint`, `composer test:types`, `composer test:type-coverage`, `composer test:unit`, and `composer test:installer` (if feature-related).
@else
4. Run all required checks: `composer test:lint`, `composer test:types`, `composer test:type-coverage`, and `composer test:unit`.
@endif
5. For user-visible changes, follow frontend stability rules (CLS = 0) and verify across multiple viewports and themes.

## Title and conventional commits

- Pull request titles must strictly follow the Conventional Commits specification: `type(scope): description`.
- Valid types include `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`.
- Enforced automatically by `.github/workflows/lint-pr-title.yml`.

## Independent review before publication

1. Remove temporary debug instrumentation and test artifacts.
2. Verify all local checks pass without warnings or regressions.
3. Fill out the selected PR template cleanly, removing HTML comments before opening the PR.
