/* @chisel-reporting */

import { Head, router } from '@inertiajs/react';
import { Activity, Download, FilterX, Search } from 'lucide-react';
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { audit as reportsAudit, index as reportsIndex } from '@/routes/reports';
import { exportMethod as reportsAuditExport } from '@/routes/reports/audit';
import type { BreadcrumbItem } from '@/types';
import type {
    AuditReportFilters,
    AvailableReportEvent,
    PaginatedReportAuditTrails,
    ReportBreakdownItem,
    ReportSummaryCard,
    ReportTimeSeriesPoint,
} from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: reportsIndex(),
    },
    {
        title: 'Audit Trail Activity',
        href: reportsAudit(),
    },
];

interface AuditReportPageProps {
    summary: ReportSummaryCard[];
    series: ReportTimeSeriesPoint[];
    breakdown: ReportBreakdownItem[];
    auditTrails: PaginatedReportAuditTrails;
    availableEvents: AvailableReportEvent[];
    filters: AuditReportFilters;
}

export default function AuditReportPage({
    summary,
    series,
    breakdown,
    auditTrails,
    availableEvents,
    filters,
}: AuditReportPageProps) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [event, setEvent] = useState(filters.event ?? 'all');
    const [search, setSearch] = useState(filters.search ?? '');

    const handleFilterSubmit = (e?: FormEvent) => {
        if (e) {
            e.preventDefault();
        }

        router.get(
            reportsAudit.url(),
            {
                date_from: dateFrom,
                date_to: dateTo,
                event: event !== 'all' ? event : undefined,
                search: search || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleResetFilters = () => {
        router.get(
            reportsAudit.url(),
            {},
            { preserveState: true, replace: true },
        );
    };

    const exportUrl = reportsAuditExport.url({
        query: {
            date_from: dateFrom,
            date_to: dateTo,
            event: event !== 'all' ? event : undefined,
            search: search || undefined,
        },
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Trail Activity Report" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Audit Trail Activity"
                        description="Track administrative actions, user security events, and audit volume."
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

                {/* Trend Chart and Breakdown Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <ReportChart
                            title="Audit Events Over Time"
                            description="Daily count of logged events across the application"
                            series={series}
                            metricLabel="Events"
                        />
                    </div>

                    <Card className="border-sidebar-border/70 dark:border-sidebar-border">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">
                                Event Distribution
                            </CardTitle>
                            <CardDescription>
                                Breakdown of events by category during the
                                selected period
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {breakdown.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    No event breakdown data available.
                                </p>
                            ) : (
                                breakdown.map((item) => (
                                    <div key={item.key} className="space-y-1">
                                        <div className="flex justify-between text-xs">
                                            <span className="max-w-[180px] truncate font-medium text-foreground">
                                                {item.label}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {item.count} ({item.percentage}
                                                %)
                                            </span>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                style={{
                                                    width: `${Math.min(item.percentage, 100)}%`,
                                                }}
                                                className="h-full rounded-full bg-primary"
                                            />
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

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

                            <div className="w-48">
                                <Label
                                    htmlFor="audit-event-filter"
                                    className="text-xs"
                                >
                                    Event Type
                                </Label>
                                <Select value={event} onValueChange={setEvent}>
                                    <SelectTrigger
                                        id="audit-event-filter"
                                        className="h-9 text-xs"
                                    >
                                        <SelectValue placeholder="Event Type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Events
                                        </SelectItem>
                                        {availableEvents.map((evt) => (
                                            <SelectItem
                                                key={evt.value}
                                                value={evt.value}
                                            >
                                                {evt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="min-w-[200px] flex-1">
                                <Label
                                    htmlFor="audit-search-filter"
                                    className="text-xs"
                                >
                                    Search
                                </Label>
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        id="audit-search-filter"
                                        type="text"
                                        placeholder="Search by IP, URL, or resource..."
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
                                <th className="px-4 py-3 font-medium">Event</th>
                                <th className="px-4 py-3 font-medium">Actor</th>
                                <th className="px-4 py-3 font-medium">
                                    Target Resource
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    IP Address
                                </th>
                                <th className="px-4 py-3 text-end font-medium">
                                    Timestamp
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {auditTrails.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="h-48 p-0">
                                        <TableEmptyState
                                            icon={Activity}
                                            title="No audit events found"
                                            description="No audit events recorded for the selected filters."
                                        />
                                    </td>
                                </tr>
                            ) : (
                                auditTrails.data.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="transition-colors hover:bg-muted/50"
                                    >
                                        <td className="px-4 py-3.5">
                                            <Badge
                                                variant="outline"
                                                className="font-mono text-xs"
                                            >
                                                {item.event_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3.5">
                                            {item.user ? (
                                                <div>
                                                    <div className="font-medium text-foreground">
                                                        {item.user.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.user.email}
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground italic">
                                                    System
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            {item.auditable_type ? (
                                                <div className="font-mono text-xs">
                                                    {item.auditable_type
                                                        .split('\\')
                                                        .pop() ??
                                                        item.auditable_type}
                                                    {item.auditable_id && (
                                                        <span className="block max-w-[120px] truncate text-[10px] text-muted-foreground">
                                                            #{item.auditable_id}
                                                        </span>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3.5 font-mono text-xs text-muted-foreground">
                                            {item.ip_address ?? '-'}
                                        </td>
                                        <td className="px-4 py-3.5 text-end text-xs text-muted-foreground">
                                            {new Date(
                                                item.created_at,
                                            ).toLocaleString()}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {auditTrails.links && auditTrails.links.length > 3 && (
                        <TablePagination
                            links={auditTrails.links}
                            from={auditTrails.from}
                            to={auditTrails.to}
                            total={auditTrails.total}
                            itemName="events"
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

/* @end-chisel-reporting */
