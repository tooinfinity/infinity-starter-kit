import { Link, router } from '@inertiajs/react';
import {
    Check,
    Info,
    ShieldAlert,
    Trash2,
    UserCheck,
    Bell,
    ExternalLink,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { destroy, markRead } from '@/routes/notifications';
import type { NotificationItem as NotificationItemType } from '@/types/notifications';

function formatRelativeTime(dateString: string): string {
    if (!dateString) {
        return '';
    }
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) {
        return 'just now';
    }
    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
        return `${diffInMinutes}m ago`;
    }
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
        return `${diffInHours}h ago`;
    }
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
        return `${diffInDays}d ago`;
    }
    return date.toLocaleDateString();
}

function getNotificationIcon(type: string | null) {
    switch (type) {
        case 'security':
            return (
                <ShieldAlert className="h-4 w-4 text-amber-600 dark:text-amber-400" />
            );
        case 'user_management':
            return (
                <UserCheck className="h-4 w-4 text-blue-600 dark:text-blue-400" />
            );
        case 'system':
            return (
                <Info className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            );
        default:
            return <Bell className="h-4 w-4 text-muted-foreground" />;
    }
}

type Props = {
    notification: NotificationItemType;
    compact?: boolean;
    onActionClick?: () => void;
};

export function NotificationItem({
    notification,
    compact = false,
    onActionClick,
}: Props) {
    const isUnread = notification.read_at === null;

    const handleMarkAsRead = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        router.patch(
            markRead({ notification: notification.id }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleDelete = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        router.delete(destroy({ notification: notification.id }).url, {
            preserveScroll: true,
        });
    };

    return (
        <div
            className={cn(
                'group relative flex items-start gap-3 rounded-lg transition-colors',
                compact
                    ? 'p-3 text-xs hover:bg-muted/60'
                    : 'border p-4 text-sm hover:bg-muted/40',
                isUnread
                    ? 'border-primary/20 bg-primary/5 dark:bg-primary/10'
                    : 'border-border/60 bg-card',
            )}
        >
            <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted">
                {getNotificationIcon(notification.type)}
            </div>

            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex items-center justify-between gap-2">
                    <p
                        className={cn(
                            'truncate leading-none font-medium',
                            isUnread
                                ? 'font-semibold text-foreground'
                                : 'text-foreground/80',
                        )}
                    >
                        {notification.title}
                    </p>
                    <span className="text-[10px] whitespace-nowrap text-muted-foreground">
                        {formatRelativeTime(notification.created_at)}
                    </span>
                </div>

                <p className="line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                    {notification.body}
                </p>

                {notification.action && (
                    <div className="pt-1">
                        <Link
                            href={notification.action.url}
                            onClick={onActionClick}
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            <span>
                                {notification.action.label || 'View details'}
                            </span>
                            <ExternalLink className="h-3 w-3" />
                        </Link>
                    </div>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-1">
                {isUnread && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7 text-muted-foreground hover:text-foreground"
                        title="Mark as read"
                        onClick={handleMarkAsRead}
                    >
                        <Check className="h-3.5 w-3.5" />
                        <span className="sr-only">Mark as read</span>
                    </Button>
                )}
                {!compact && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive"
                        title="Delete notification"
                        onClick={handleDelete}
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                        <span className="sr-only">Delete</span>
                    </Button>
                )}
            </div>
        </div>
    );
}
