/* @chisel-notifications */

import { Link, router, usePage } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import { NotificationEmptyState } from '@/components/notifications/notification-empty-state';
import { NotificationItem } from '@/components/notifications/notification-item';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuContent,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { index, markAllRead } from '@/routes/notifications';

type Props = {
    onClose?: () => void;
};

export function NotificationDropdown({ onClose }: Props) {
    const { notifications } = usePage().props;
    const recent = notifications?.recent ?? [];
    const unreadCount = notifications?.unreadCount ?? 0;

    const handleMarkAllAsRead = (e: React.MouseEvent) => {
        e.preventDefault();
        router.patch(
            markAllRead().url,
            {},
            {
                preserveScroll: true,
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
                    <span className="text-sm font-semibold">Notifications</span>
                    {unreadCount > 0 && (
                        <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                            {unreadCount} unread
                        </span>
                    )}
                </div>
                {unreadCount > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-auto px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                        onClick={handleMarkAllAsRead}
                    >
                        <CheckCheck className="mr-1 h-3.5 w-3.5" />
                        Mark all as read
                    </Button>
                )}
            </div>

            <div className="max-h-[350px] divide-y divide-border/40 overflow-y-auto p-1">
                {recent.length === 0 ? (
                    <NotificationEmptyState
                        title="No notifications"
                        description="You are caught up with all notifications."
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
                    <Link href={index().url}>View all notifications</Link>
                </Button>
            </div>
        </DropdownMenuContent>
    );
}

/* @end-chisel-notifications */
