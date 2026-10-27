import { Link, router, usePage } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NotificationEmptyState } from '@/components/notifications/notification-empty-state';
import { NotificationItem } from '@/components/notifications/notification-item';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuContent,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { index, markAllRead } from '@/routes/notifications';

function NotificationItemSkeleton() {
    return (
        <div className="flex items-start gap-3 rounded-lg p-3">
            <Skeleton className="mt-0.5 h-8 w-8 shrink-0 rounded-full" />
            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex items-center justify-between gap-2">
                    <Skeleton className="h-3.5 w-24" />
                    <Skeleton className="h-3 w-12" />
                </div>
                <div className="space-y-1 pt-0.5">
                    <Skeleton className="h-3 w-full" />
                    <Skeleton className="h-3 w-2/3" />
                </div>
            </div>
            <Skeleton className="h-7 w-7 shrink-0 rounded-md" />
        </div>
    );
}

function NotificationDropdownSkeleton({
    className,
    'aria-hidden': ariaHidden,
}: {
    className?: string;
    'aria-hidden'?: boolean | 'true' | 'false';
}) {
    return (
        <div
            className={cn('divide-y divide-border/40', className)}
            aria-hidden={ariaHidden}
        >
            <NotificationItemSkeleton />
            <NotificationItemSkeleton />
            <NotificationItemSkeleton />
        </div>
    );
}

type Props = {
    onClose?: () => void;
};

export function NotificationDropdown({ onClose }: Props) {
    const { __, transChoice } = useTranslation();
    const { notifications } = usePage().props;
    const recent = notifications?.recent;
    const unreadCount = notifications?.unreadCount ?? 0;
    const [showSkeleton, setShowSkeleton] = useState(false);
    const [isMarkingAll, setIsMarkingAll] = useState(false);

    useEffect(() => {
        if (recent !== undefined) {
            return;
        }

        const timer = setTimeout(() => {
            setShowSkeleton(true);
        }, 300);

        return () => clearTimeout(timer);
    }, [recent]);

    const isSkeletonVisible = recent === undefined && showSkeleton;

    const handleMarkAllAsRead = (e: React.MouseEvent) => {
        e.preventDefault();
        setIsMarkingAll(true);
        router.patch(
            markAllRead().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsMarkingAll(false);
                },
                onSuccess: () => {
                    router.reload({ only: ['notifications.recent'] });
                },
            },
        );
    };

    return (
        <DropdownMenuContent
            className="w-80 p-0 sm:w-96"
            align="end"
            sideOffset={8}
        >
            <div className="flex items-center justify-between border-b px-4 py-3">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold">
                        {__('Notifications')}
                    </span>
                    {unreadCount > 0 && (
                        <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary tabular-nums">
                            {transChoice(':count unread', unreadCount)}
                        </span>
                    )}
                </div>
                {unreadCount > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={isMarkingAll}
                        className="h-auto px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                        onClick={handleMarkAllAsRead}
                    >
                        <CheckCheck className="me-1 h-3.5 w-3.5" />
                        {__('Mark all as read')}
                    </Button>
                )}
            </div>

            <div className="max-h-[350px] divide-y divide-border/40 overflow-y-auto p-1">
                {recent === undefined ? (
                    <NotificationDropdownSkeleton
                        className={isSkeletonVisible ? undefined : 'invisible'}
                        aria-hidden={!isSkeletonVisible}
                    />
                ) : recent.length === 0 ? (
                    <NotificationEmptyState
                        title={__('No notifications')}
                        description={__(
                            'You are caught up with all notifications.',
                        )}
                        className="py-8"
                    />
                ) : (
                    recent.map((item) => (
                        <NotificationItem
                            key={item.id}
                            notification={item}
                            compact
                            onActionClick={onClose}
                        />
                    ))
                )}
            </div>

            <DropdownMenuSeparator className="m-0" />

            <div className="p-2 text-center">
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-full text-xs font-medium text-primary"
                    asChild
                    onClick={onClose}
                >
                    <Link href={index().url}>
                        {__('View all notifications')}
                    </Link>
                </Button>
            </div>
        </DropdownMenuContent>
    );
}
