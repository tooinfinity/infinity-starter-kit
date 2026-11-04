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

@boostsnippet('Example action class', 'php')
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
@endboostsnippet
