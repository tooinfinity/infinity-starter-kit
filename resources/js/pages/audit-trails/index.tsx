import { Head, router, usePage } from '@inertiajs/react';
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
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
import { index as auditTrailsIndex } from '@/routes/audit-trails';
import type { BreadcrumbItem } from '@/types';
import type {
    AuditEventName,
    AuditTrailFilters,
    AuditTrailItem,
    AvailableEvent,
    PaginatedAuditTrails,
} from '@/types/audit-trails';

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
    const { locale, timezone } = usePage().props;
    const { __ } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [event, setEvent] = useState(filters.event ?? 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [selectedAudit, setSelectedAudit] = useState<AuditTrailItem | null>(
        null,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Audit Trails'),
            href: auditTrailsIndex(),
        },
    ];

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
            <Head title={__('Audit Trails')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight text-foreground">
                                {__('Audit Trails')}
                            </h1>
                            <Badge
                                variant="secondary"
                                className="text-xs font-medium"
                            >
                                {auditTrails.total ?? 0}{' '}
                                {auditTrails.total === 1
                                    ? __('record')
                                    : __('records')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {__(
                                'Track and monitor administrative and security events.',
                            )}
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-xl border bg-card p-4 text-card-foreground shadow-sm">
                    <form onSubmit={handleFilterSubmit} className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="relative">
                                <Search className="absolute start-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input
                                    placeholder={__(
                                        'Search by IP, target ID, or user...',
                                    )}
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="ps-8 pe-8"
                                    data-test="audit-search-input"
                                />
                                {search && (
                                    <button
                                        type="button"
                                        onClick={handleClearSearch}
                                        className="absolute end-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                                        aria-label={__('Clear search')}
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
                                        <SelectValue
                                            placeholder={__('All Events')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            {__('All Events')}
                                        </SelectItem>
                                        {availableEvents.map((evt) => (
                                            <SelectItem
                                                key={evt.value}
                                                value={evt.value}
                                            >
                                                {__(evt.label)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Input
                                    type="date"
                                    placeholder={__('From Date')}
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                    aria-label={__('From Date')}
                                    data-test="audit-date-from"
                                />
                            </div>

                            <div>
                                <Input
                                    type="date"
                                    placeholder={__('To Date')}
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    aria-label={__('To Date')}
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
                                    {__('Reset')}
                                </Button>
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                className="text-xs"
                                data-test="audit-filter-submit"
                            >
                                {__('Apply Filters')}
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Audit Trails Table */}
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3.5">
                                        {__('Event')}
                                    </th>
                                    <th className="px-4 py-3.5">
                                        {__('Actor')}
                                    </th>
                                    <th className="px-4 py-3.5">
                                        {__('Target')}
                                    </th>
                                    <th className="px-4 py-3.5">
                                        {__('IP Address')}
                                    </th>
                                    <th className="px-4 py-3.5">
                                        {__('Timestamp')}
                                    </th>
                                    <th className="px-4 py-3.5 text-end">
                                        {__('Actions')}
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
                                                        ? __(
                                                              'No matching audit records',
                                                          )
                                                        : __(
                                                              'No audit trails found',
                                                          )
                                                }
                                                description={
                                                    hasActiveFilters
                                                        ? __(
                                                              'No events matched your filter criteria. Try changing or clearing your filters.',
                                                          )
                                                        : __(
                                                              'Events will appear here as system actions are performed.',
                                                          )
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
                                                            {__(
                                                                'Reset filters',
                                                            )}
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
                                                            __(
                                                                audit.event_label,
                                                            ),
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
                                                ) : audit.actor_name ? (
                                                    <div className="flex items-center gap-2.5">
                                                        <Avatar className="size-7 shrink-0">
                                                            <AvatarFallback className="bg-muted text-[10px] font-medium text-foreground uppercase">
                                                                {getInitials(
                                                                    audit.actor_name,
                                                                )}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="flex flex-col">
                                                            <div className="flex items-center gap-1.5">
                                                                <p className="font-medium text-foreground">
                                                                    {
                                                                        audit.actor_name
                                                                    }
                                                                </p>
                                                                <Badge
                                                                    variant="outline"
                                                                    className="px-1 py-0 text-[10px] font-normal text-muted-foreground"
                                                                >
                                                                    {__(
                                                                        'Deleted',
                                                                    )}
                                                                </Badge>
                                                            </div>
                                                            {audit.actor_email && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    {
                                                                        audit.actor_email
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs font-normal"
                                                    >
                                                        {__('System')}
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
                                                {formatDateTime(
                                                    audit.created_at,
                                                    locale,
                                                    timezone,
                                                )}
                                            </td>

                                            <td className="px-4 py-3.5 text-end">
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
                                                    {__('Details')}
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
