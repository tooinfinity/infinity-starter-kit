/* @chisel-audit-trails */

import { Head, Link, router } from '@inertiajs/react';
import { Eye, FilterX, History, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AuditTrailDetail } from '@/components/audit-trails/audit-trail-detail';
import Heading from '@/components/heading';
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

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <Heading
                    title="Audit Trails"
                    description="Track and monitor administrative and security events."
                />

                {/* Filters */}
                <div className="rounded-lg border bg-card p-4 text-card-foreground shadow-sm">
                    <form onSubmit={handleFilterSubmit} className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by IP, URL, or user..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-8"
                                    data-test="audit-search-input"
                                />
                            </div>

                            <div>
                                <Select
                                    value={event}
                                    onValueChange={(val) => {
                                        setEvent(val);
                                    }}
                                >
                                    <SelectTrigger data-test="audit-event-select">
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
                <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Event</th>
                                    <th className="px-4 py-3">Actor</th>
                                    <th className="px-4 py-3">Target</th>
                                    <th className="px-4 py-3">IP Address</th>
                                    <th className="px-4 py-3">Timestamp</th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {auditTrails.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <History className="size-8 opacity-40" />
                                                <p className="font-medium">
                                                    No audit trails found
                                                </p>
                                                <p className="text-xs">
                                                    Events will appear here as
                                                    system actions are
                                                    performed.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    auditTrails.data.map((audit) => (
                                        <tr
                                            key={audit.id}
                                            className="transition-colors hover:bg-muted/30"
                                            data-test={`audit-row-${audit.id}`}
                                        >
                                            <td className="px-4 py-3 font-medium">
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

                                            <td className="px-4 py-3">
                                                {audit.user ? (
                                                    <div>
                                                        <p className="font-medium text-foreground">
                                                            {audit.user.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {audit.user.email}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        System
                                                    </Badge>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
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

                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {audit.ip_address ?? '—'}
                                            </td>

                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                {new Date(
                                                    audit.created_at,
                                                ).toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3 text-right">
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
                    {auditTrails.links && auditTrails.links.length > 3 && (
                        <div className="flex items-center justify-between border-t bg-muted/20 px-4 py-3">
                            <div className="text-xs text-muted-foreground">
                                Showing {auditTrails.from ?? 0} to{' '}
                                {auditTrails.to ?? 0} of{' '}
                                {auditTrails.total ?? 0} audit records
                            </div>
                            <div className="flex gap-1">
                                {auditTrails.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                        asChild={Boolean(link.url)}
                                        className="h-8 text-xs"
                                    >
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        ) : (
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
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

/* @end-chisel-audit-trails */
