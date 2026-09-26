<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Requests\Reporting\UserReportRequest;
use App\Models\User;
use App\Queries\Reporting\UserReportQuery;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Traits\HasRoles;

final readonly class UserReportController
{
    public function __invoke(UserReportRequest $request, UserReportQuery $query): Response
    {
        $filters = $request->filters();
        $perPage = $request->integer('per_page', 15);

        $result = $query->handle($filters, $perPage);

        $users = $result['paginated']->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => trait_exists(HasRoles::class) ? $user->roles->pluck('name')->all() : [],
            'created_at' => $user->created_at->toISOString(),
        ]);

        return Inertia::render('reports/users', [
            'summary' => $result['summary'],
            'series' => $result['series'],
            'users' => $users,
            'filters' => [
                'date_from' => $result['date_from'],
                'date_to' => $result['date_to'],
                'status' => $filters['status'] ?? 'all',
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? 'created_at',
                'direction' => $filters['direction'] ?? 'desc',
            ],
        ]);
    }
}
