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

@boostsnippet('Example controller', 'php')
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
@endboostsnippet
