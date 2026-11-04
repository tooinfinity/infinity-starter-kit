# GitHub workflows guidelines

Workflows in this repository only ever read the code and report whether it passes. They never write to the repository, GitHub, or production. A workflow that can write is a workflow an attacker can use through a dependency, fork, or pull request.

## Read-only, always

- Every workflow declares exactly this at the top level, and nothing else:

@boostsnippet('Workflow permissions', 'yaml')
permissions:
  contents: read
@endboostsnippet

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
