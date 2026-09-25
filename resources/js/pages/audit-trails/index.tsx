import { Head, router } from '@inertiajs/react';
import { Eye, FilterX, History, Search, X } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AuditTrailDetail } from '@/components/audit-trails/audit-trail-detail';
import { TableEmptyState } from '@/components/table-empty-state';
import { TablePagination } from '@/components/table-pagination';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { index as auditTrailsIndex } from '@/routes/audit-trails';
import type { BreadcrumbItem } from '@/types';
import type {
    AuditEventName,
    AuditTrailFilters,
    AuditTrailItem,
    AvailableEvent,
    PaginatedAuditTrails,
} from '@/types/audit-trails';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Audit Trails',
        href: auditTrailsIndex(),
    },
];

type Props = {
    auditTrails: PaginatedAuditTrails;
    filters: AuditTrailFilters;
    availableEvents: AvailableEvent[];
};

export default function AuditTrailsIndex({
    auditTrails,
    filters,
    availableEvents,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [event, setEvent] = useState(filters.event ?? 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [selectedAudit, setSelectedAudit] = useState<AuditTrailItem | null>(
        null,
    );

    const getInitials = useInitials();

    const handleFilterSubmit = (e?: FormEvent) => {
        if (e) {
            e.preventDefault();
        }

        router.get(
            auditTrailsIndex.url(),
            {
                search: search || undefined,
                event: event !== 'all' ? event : undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleClearSearch = () => {
        setSearch('');
        router.get(
            auditTrailsIndex.url(),
            {
                search: undefined,
                event: event !== 'all' ? event : undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleResetFilters = () => {
        setSearch('');
        setEvent('all');
        setDateFrom('');
        setDateTo('');

        router.get(
            auditTrailsIndex.url(),
            {},
            { preserveState: true, replace: true },
        );
    };

    const renderEventBadge = (eventName: AuditEventName, label: string) => {
        switch (eventName) {
            case 'user.created':
            case 'user.activated':
                return (
                    <Badge
                        variant="outline"
                        className="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                    >
                        {label}
                    </Badge>
                );
            case 'user.deleted':
            case 'user.deactivated':
                return (
                    <Badge
                        variant="outline"
                        className="border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-400"
                    >
                        {label}
                    </Badge>
                );
            case 'user.updated':
            case 'user.password_changed':
                return (
                    <Badge
                        variant="outline"
                        className="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400"
                    >
                        {label}
                    </Badge>
                );
            case 'settings.updated':
                return (
                    <Badge
                        variant="outline"
                        className="border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-400"
                    >
                        {label}
                    </Badge>
                );
            default:
                return <Badge variant="secondary">{label}</Badge>;
        }
    };

    const hasActiveFilters =
        Boolean(search) ||
        event !== 'all' ||
        Boolean(dateFrom) ||
        Boolean(dateTo);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Trails" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight text-foreground">
                                Audit Trails
                            </h1>
                            <Badge
                                variant="secondary"
                                className="text-xs font-medium"
                            >
                                {auditTrails.total ?? 0}{' '}
                                {auditTrails.total === 1 ? 'record' : 'records'}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Track and monitor administrative and security
                            events.
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-xl border bg-card p-4 text-card-foreground shadow-sm">
                    <form onSubmit={handleFilterSubmit} className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by IP, URL, or user..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pr-8 pl-8"
                                    data-test="audit-search-input"
                                />
                                {search && (
                                    <button
                                        type="button"
                                        onClick={handleClearSearch}
                                        className="absolute top-2.5 right-2.5 text-muted-foreground hover:text-foreground"
                                        aria-label="Clear search"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>

                            <div>
                                <Select
                                    value={event}
                                    onValueChange={(val) => {
                                        setEvent(val);
                                    }}
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        data-test="audit-event-select"
                                    >
                                        <SelectValue placeholder="All Events" />
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

                            <div>
                                <Input
                                    type="date"
                                    placeholder="From Date"
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                    aria-label="From Date"
                                    data-test="audit-date-from"
                                />
                            </div>

                            <div>
                                <Input
                                    type="date"
                                    placeholder="To Date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    aria-label="To Date"
                                    data-test="audit-date-to"
                                />
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-1">
                            {hasActiveFilters && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleResetFilters}
                                    className="gap-1 text-xs"
                                    data-test="audit-reset-filters"
                                >
                                    <FilterX className="size-3.5" />
                                    Reset
                                </Button>
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                className="text-xs"
                                data-test="audit-filter-submit"
                            >
                                Apply Filters
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Audit Trails Table */}
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3.5">Event</th>
                                    <th className="px-4 py-3.5">Actor</th>
                                    <th className="px-4 py-3.5">Target</th>
                                    <th className="px-4 py-3.5">IP Address</th>
                                    <th className="px-4 py-3.5">Timestamp</th>
                                    <th className="px-4 py-3.5 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {auditTrails.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-0">
                                            <TableEmptyState
                                                icon={History}
                                                title={
                                                    hasActiveFilters
                                                        ? 'No matching audit records'
                                                        : 'No audit trails found'
                                                }
                                                description={
                                                    hasActiveFilters
                                                        ? 'No events matched your filter criteria. Try changing or clearing your filters.'
                                                        : 'Events will appear here as system actions are performed.'
                                                }
                                                action={
                                                    hasActiveFilters ? (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={
                                                                handleResetFilters
                                                            }
                                                            className="gap-1.5 text-xs"
                                                        >
                                                            <FilterX className="size-3.5" />
                                                            Reset filters
                                                        </Button>
                                                    ) : undefined
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    auditTrails.data.map((audit) => (
                                        <tr
                                            key={audit.id}
                                            className="transition-colors hover:bg-muted/30"
                                            data-test={`audit-row-${audit.id}`}
                                        >
                                            <td className="px-4 py-3.5 font-medium">
                                                <div className="flex flex-col gap-1">
                                                    <div>
                                                        {renderEventBadge(
                                                            audit.event,
                                                            audit.event_label,
                                                        )}
                                                    </div>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {audit.event}
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3.5">
                                                {audit.user ? (
                                                    <div className="flex items-center gap-2.5">
                                                        <Avatar className="size-7 shrink-0">
                                                            <AvatarFallback className="bg-muted text-[10px] font-medium text-foreground uppercase">
                                                                {getInitials(
                                                                    audit.user
                                                                        .name,
                                                                )}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="flex flex-col">
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    audit.user
                                                                        .name
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    audit.user
                                                                        .email
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs font-normal"
                                                    >
                                                        System
                                                    </Badge>
                                                )}
                                            </td>

                                            <td className="px-4 py-3.5 font-mono text-xs text-muted-foreground">
                                                {audit.auditable_type ? (
                                                    <div>
                                                        <p className="text-foreground">
                                                            {audit.auditable_type
                                                                .split('\\')
                                                                .pop()}
                                                        </p>
                                                        <p className="max-w-[150px] truncate text-[11px]">
                                                            {audit.auditable_id}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>

                                            <td className="px-4 py-3.5 font-mono text-xs text-muted-foreground">
                                                {audit.ip_address ?? '—'}
                                            </td>

                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {new Date(
                                                    audit.created_at,
                                                ).toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3.5 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setSelectedAudit(audit)
                                                    }
                                                    className="gap-1.5 text-xs"
                                                    data-test={`view-audit-${audit.id}`}
                                                >
                                                    <Eye className="size-3.5" />
                                                    Details
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    <TablePagination
                        links={auditTrails.links}
                        from={auditTrails.from}
                        to={auditTrails.to}
                        total={auditTrails.total}
                        itemName="audit records"
                    />
                </div>
            </div>

            {/* Detail Dialog */}
            <AuditTrailDetail
                audit={selectedAudit}
                open={Boolean(selectedAudit)}
                onOpenChange={(open) => !open && setSelectedAudit(null)}
            />
        </AppLayout>
    );
}
