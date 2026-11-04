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

@boostsnippet('Example form request', 'php')
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
@endboostsnippet
