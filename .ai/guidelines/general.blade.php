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
