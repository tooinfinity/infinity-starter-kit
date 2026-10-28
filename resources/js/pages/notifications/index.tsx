import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { NotificationEmptyState } from '@/components/notifications/notification-empty-state';
import { NotificationItem } from '@/components/notifications/notification-item';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { index, markAllRead } from '@/routes/notifications';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedNotifications } from '@/types/notifications';

type Props = {
    notifications: PaginatedNotifications;
};

export default function NotificationsIndex({ notifications }: Props) {
    const { __ } = useTranslation();
    const { notifications: sharedNotifications } = usePage().props;
    const unreadCount = sharedNotifications?.unreadCount ?? 0;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Notifications'),
            href: index(),
        },
    ];

    const handleMarkAllAsRead = () => {
        router.patch(
            markAllRead().url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Notifications')} />

            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title={__('Notifications')}
                        description={__(
                            'View and manage your recent activity and system alerts.',
                        )}
                    />

                    {unreadCount > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleMarkAllAsRead}
                            className="self-start sm:self-auto"
                        >
                            <CheckCheck className="me-2 h-4 w-4" />
                            {__('Mark all as read')}
                        </Button>
                    )}
                </div>

                <div className="mt-2 space-y-3">
                    {notifications.data.length === 0 ? (
                        <div className="rounded-xl border border-dashed bg-card p-12">
                            <NotificationEmptyState />
                        </div>
                    ) : (
                        notifications.data.map((notification) => (
                            <NotificationItem
                                key={notification.id}
                                notification={notification}
                            />
                        ))
                    )}
                </div>

                {notifications.links && notifications.links.length > 3 && (
                    <div className="mt-6 flex items-center justify-center gap-1">
                        {notifications.links.map((link, index) => {
                            let label = link.label;
                            if (
                                label.includes('Previous') ||
                                label.includes('&laquo;')
                            ) {
                                label = __('Previous');
                            } else if (
                                label.includes('Next') ||
                                label.includes('&raquo;')
                            ) {
                                label = __('Next');
                            } else if (
                                label === '...' ||
                                label === '&hellip;'
                            ) {
                                label = '…';
                            }

                            if (!link.url) {
                                return (
                                    <span
                                        key={index}
                                        className="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs text-muted-foreground opacity-50"
                                    >
                                        {label}
                                    </span>
                                );
                            }

                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveScroll
                                    className={`inline-flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium transition-colors ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted/50 text-foreground hover:bg-muted'
                                    }`}
                                >
                                    {label}
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
