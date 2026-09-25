import { ArrowRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AuditTrailItem } from '@/types/audit-trails';

type Props = {
    audit: AuditTrailItem | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function AuditTrailDetail({ audit, open, onOpenChange }: Props) {
    if (!audit) {
        return null;
    }

    const allKeys = Array.from(
        new Set([
            ...Object.keys(audit.old_values ?? {}),
            ...Object.keys(audit.new_values ?? {}),
        ]),
    );

    const formatValue = (val: unknown): string => {
        if (val === null || val === undefined) {
            return '—';
        }
        if (typeof val === 'boolean') {
            return val ? 'true' : 'false';
        }
        if (typeof val === 'number' || typeof val === 'string') {
            return String(val);
        }
        return JSON.stringify(val, null, 2);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <DialogTitle className="text-xl">
                            {audit.event_label || audit.event}
                        </DialogTitle>
                        <Badge variant="outline" className="font-mono text-xs">
                            {audit.event}
                        </Badge>
                    </div>
                    <DialogDescription>
                        Recorded at{' '}
                        {new Date(audit.created_at).toLocaleString()}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-2 text-sm">
                    {/* Metadata Grid */}
                    <div className="grid grid-cols-1 gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2">
                        <div>
                            <span className="text-xs font-medium text-muted-foreground">
                                Actor
                            </span>
                            <div className="mt-1 font-medium">
                                {audit.user ? (
                                    <div>
                                        <p>{audit.user.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {audit.user.email}
                                        </p>
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground italic">
                                        System / Anonymous
                                    </span>
                                )}
                            </div>
                        </div>

                        <div>
                            <span className="text-xs font-medium text-muted-foreground">
                                Target
                            </span>
                            <div className="mt-1">
                                {audit.auditable_type ? (
                                    <div>
                                        <p className="font-mono text-xs">
                                            {audit.auditable_type
                                                .split('\\')
                                                .pop()}
                                        </p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {audit.auditable_id}
                                        </p>
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                )}
                            </div>
                        </div>

                        <div>
                            <span className="text-xs font-medium text-muted-foreground">
                                IP Address
                            </span>
                            <p className="mt-1 font-mono text-xs">
                                {audit.ip_address ?? '—'}
                            </p>
                        </div>

                        <div>
                            <span className="text-xs font-medium text-muted-foreground">
                                URL
                            </span>
                            <p className="mt-1 font-mono text-xs break-all">
                                {audit.url ?? '—'}
                            </p>
                        </div>

                        {audit.tags && audit.tags.length > 0 && (
                            <div className="sm:col-span-2">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Tags
                                </span>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {audit.tags.map((tag) => (
                                        <Badge
                                            key={tag}
                                            variant="secondary"
                                            className="text-xs"
                                        >
                                            {tag}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {audit.user_agent && (
                            <div className="sm:col-span-2">
                                <span className="text-xs font-medium text-muted-foreground">
                                    User Agent
                                </span>
                                <p className="mt-1 font-mono text-xs break-all text-muted-foreground">
                                    {audit.user_agent}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Changes Diff Table */}
                    <div>
                        <h4 className="mb-3 text-sm font-semibold">
                            Changes Diff
                        </h4>
                        {allKeys.length === 0 ? (
                            <p className="text-xs text-muted-foreground italic">
                                No property changes recorded for this event.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-md border">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b bg-muted/50 font-medium text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2">Field</th>
                                            <th className="px-3 py-2">
                                                Old Value
                                            </th>
                                            <th className="px-3 py-2"></th>
                                            <th className="px-3 py-2">
                                                New Value
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y font-mono">
                                        {allKeys.map((key) => {
                                            const oldVal =
                                                audit.old_values?.[key];
                                            const newVal =
                                                audit.new_values?.[key];
                                            const isChanged =
                                                audit.old_values !== null &&
                                                audit.new_values !== null &&
                                                JSON.stringify(oldVal) !==
                                                    JSON.stringify(newVal);

                                            return (
                                                <tr
                                                    key={key}
                                                    className={
                                                        isChanged
                                                            ? 'bg-amber-500/5'
                                                            : undefined
                                                    }
                                                >
                                                    <td className="px-3 py-2 font-medium text-foreground">
                                                        {key}
                                                    </td>
                                                    <td className="max-w-[200px] px-3 py-2 break-all text-rose-600 dark:text-rose-400">
                                                        {formatValue(oldVal)}
                                                    </td>
                                                    <td className="px-1 py-2 text-muted-foreground">
                                                        {isChanged && (
                                                            <ArrowRight className="size-3" />
                                                        )}
                                                    </td>
                                                    <td className="max-w-[200px] px-3 py-2 break-all text-emerald-600 dark:text-emerald-400">
                                                        {formatValue(newVal)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
