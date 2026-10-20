<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportUserReportStream
{
    public function __construct(
        private UserReportQuery $reportQuery,
    ) {}

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, status?: string|null, search?: string|null}  $filters
     */
    public function handle(array $filters = []): StreamedResponse
    {
        $timezone = $this->resolveTimezone();

        $dateFromStr = ! empty($filters['date_from'])
            ? $filters['date_from']
            : Date::now($timezone)->subDays(29)->toDateString();

        $dateToStr = ! empty($filters['date_to'])
            ? $filters['date_to']
            : Date::now($timezone)->toDateString();

        $from = Date::parse($dateFromStr, $timezone)->startOfDay()->utc();
        $to = Date::parse($dateToStr, $timezone)->endOfDay()->utc();

        /** @var Builder<User> $query */
        $query = $this->reportQuery->buildFilteredQuery($filters, $from, $to)->latest('created_at');

        $fileName = sprintf('users-report-%s.csv', Date::now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($query): void {
            /** @var resource $handle */
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel / Unicode compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('reports.export.columns.id'),
                __('reports.export.columns.name'),
                __('reports.export.columns.email'),
                __('reports.export.columns.status'),
                __('reports.export.columns.roles'),
                __('reports.export.columns.created_at'),
            ],
                escape: '\\');

            foreach ($query->lazy(500) as $user) {
                /** @var User $user */
                $roleNames = [];
                if (trait_exists(HasRoles::class)) {
                    foreach ($user->roles as $role) {
                        $name = $role->getAttribute('name');
                        if (is_string($name)) {
                            $roleNames[] = $name;
                        }
                    }
                }

                $roles = implode(', ', $roleNames);

                fputcsv($handle, [
                    $user->id,
                    $this->sanitizeValue($user->name),
                    $this->sanitizeValue($user->email),
                    $user->is_active ? __('reports.common.active') : __('reports.common.inactive'),
                    $this->sanitizeValue($roles),
                    $user->created_at->toISOString(),
                ],
                    escape: '\\');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function sanitizeValue(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function resolveTimezone(): string
    {
        $timezone = config('app.display_timezone');

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        return config()->string('app.timezone', 'UTC');
    }
}
