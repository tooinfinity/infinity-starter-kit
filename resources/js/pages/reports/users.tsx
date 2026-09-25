import { Head, router } from '@inertiajs/react';
import { Download, FilterX, Search, Users as UsersIcon } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Can } from '@/components/can';
import Heading from '@/components/heading';
import { ReportChart } from '@/components/reports/report-chart';
import { ReportDateRangeFilter } from '@/components/reports/report-date-range-filter';
import { ReportSummaryCards } from '@/components/reports/report-summary-cards';
import { TableEmptyState } from '@/components/table-empty-state';
import { TablePagination } from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { index as reportsIndex, users as reportsUsers } from '@/routes/reports';
import { exportMethod as reportsUsersExport } from '@/routes/reports/users';
import type { BreadcrumbItem } from '@/types';
import type {
    PaginatedReportUsers,
    ReportSummaryCard,
    ReportTimeSeriesPoint,
    UserReportFilters,
} from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: reportsIndex(),
    },
    {
        title: 'User Activity & Growth',
        href: reportsUsers(),
    },
];

interface UserReportPageProps {
    summary: ReportSummaryCard[];
    series: ReportTimeSeriesPoint[];
    users: PaginatedReportUsers;
    filters: UserReportFilters;
}

export default function UserReportPage({
    summary,
    series,
    users,
    filters,
}: UserReportPageProps) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [search, setSearch] = useState(filters.search ?? '');

    const handleFilterSubmit = (e?: FormEvent) => {
        if (e) {
            e.preventDefault();
        }

        router.get(
            reportsUsers.url(),
            {
                date_from: dateFrom,
                date_to: dateTo,
                status: status !== 'all' ? status : undefined,
                search: search || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleResetFilters = () => {
        router.get(
            reportsUsers.url(),
            {},
            { preserveState: true, replace: true },
        );
    };

    const exportUrl = reportsUsersExport.url({
        query: {
            date_from: dateFrom,
            date_to: dateTo,
            status: status !== 'all' ? status : undefined,
            search: search || undefined,
        },
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Activity & Growth Report" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="User Activity & Growth"
                        description="Analyze user registrations, active states, and growth patterns."
                    />

                    <Can permission="reports.export">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="gap-1.5 self-start sm:self-auto"
                        >
                            <a href={exportUrl} download>
                                <Download className="size-4" />
                                <span>Export CSV</span>
                            </a>
                        </Button>
                    </Can>
                </div>

                {/* Summary Cards */}
                <ReportSummaryCards cards={summary} />

                {/* Visual Trend Chart */}
                <ReportChart
                    title="User Registrations Over Time"
                    description="Daily count of new user accounts created during the selected period"
                    series={series}
                    metricLabel="Users"
                />

                {/* Filter Controls */}
                <Card className="border-sidebar-border/70 dark:border-sidebar-border">
                    <CardContent className="pt-6">
                        <form
                            onSubmit={handleFilterSubmit}
                            className="flex flex-wrap items-end gap-4"
                        >
                            <ReportDateRangeFilter
                                dateFrom={dateFrom}
                                dateTo={dateTo}
                                onDateFromChange={setDateFrom}
                                onDateToChange={setDateTo}
                            />

                            <div className="w-36">
                                <Label
                                    htmlFor="user-status-filter"
                                    className="text-xs"
                                >
                                    Status
                                </Label>
                                <Select
                                    value={status}
                                    onValueChange={setStatus}
                                >
                                    <SelectTrigger
                                        id="user-status-filter"
                                        className="h-9 text-xs"
                                    >
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Statuses
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Inactive
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="min-w-[200px] flex-1">
                                <Label
                                    htmlFor="user-search-filter"
                                    className="text-xs"
                                >
                                    Search
                                </Label>
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        id="user-search-filter"
                                        type="text"
                                        placeholder="Search by name or email..."
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        className="h-9 pl-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button type="submit" size="sm" className="h-9">
                                    Filter
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleResetFilters}
                                    className="h-9 gap-1.5"
                                >
                                    <FilterX className="size-3.5" />
                                    <span>Reset</span>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Tabular Records */}
                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">User</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">Roles</th>
                                <th className="px-4 py-3 text-end font-medium">
                                    Registered
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {users.data.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="h-48 p-0">
                                        <TableEmptyState
                                            icon={UsersIcon}
                                            title="No users found"
                                            description="No user registrations match the selected filters."
                                        />
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="transition-colors hover:bg-muted/50"
                                    >
                                        <td className="px-4 py-3.5">
                                            <div className="font-medium text-foreground">
                                                {user.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3.5">
                                            {user.is_active ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                                                >
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-400"
                                                >
                                                    Inactive
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.length > 0 ? (
                                                    user.roles.map((role) => (
                                                        <Badge
                                                            key={role}
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            {role}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3.5 text-end text-xs text-muted-foreground">
                                            {new Date(
                                                user.created_at,
                                            ).toLocaleDateString(undefined, {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric',
                                            })}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {users.links && users.links.length > 3 && (
                        <TablePagination
                            links={users.links}
                            from={users.from}
                            to={users.to}
                            total={users.total}
                            itemName="users"
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
