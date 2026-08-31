<?php

declare(strict_types=1);

namespace App\Queries\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Traits\HasRoles;

final readonly class UserListingQuery
{
    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        /** @var Builder<User> $query */
        $query = User::query();

        if (trait_exists(HasRoles::class)) {
            $query->with('roles');
        }

        if (! empty($filters['search'])) {
            $search = mb_trim($filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('email', 'like', sprintf('%%%s%%', $search));
            });
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return $query->latest('created_at')->paginate($perPage)->withQueryString();
    }
}
