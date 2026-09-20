import { type LucideIcon } from 'lucide-react';
import React from 'react';
import { cn } from '@/lib/utils';

interface TableEmptyStateProps {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: React.ReactNode;
    className?: string;
}

export function TableEmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: TableEmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 px-4 py-12 text-center text-muted-foreground',
                className,
            )}
        >
            <div className="flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground shadow-xs">
                <Icon className="size-6 opacity-70" />
            </div>
            <div className="space-y-1">
                <p className="text-sm font-semibold text-foreground">{title}</p>
                <p className="max-w-sm text-xs text-muted-foreground">
                    {description}
                </p>
            </div>
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
