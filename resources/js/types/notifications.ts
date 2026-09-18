/* @chisel-notifications */

export type NotificationType = 'security' | 'user_management' | 'system';

export type NotificationAction = {
    url: string;
    label?: string;
};

export type NotificationItem = {
    id: string;
    type: NotificationType | null;
    title: string;
    body: string;
    icon: string | null;
    action: NotificationAction | null;
    read_at: string | null;
    created_at: string;
};

export type PaginatedNotifications = {
    data: NotificationItem[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
};

export type NotificationPreference = {
    type: string;
    label: string;
    mandatory: boolean;
    database_enabled: boolean;
};

export type NotificationsSharedData = {
    unreadCount: number;
    recent: NotificationItem[];
};

/* @end-chisel-notifications */
