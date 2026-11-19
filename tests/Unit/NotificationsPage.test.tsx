/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, it, vi } from 'vite-plus/test';
import type { NotificationItem, PaginatedNotifications } from '@/types/notifications';
import { find, findAll, hookHarness } from './HookHarness';

type GenericProps = Record<string, unknown> & {
    children?: ReactNode;
    onClick?: () => void;
    notification?: NotificationItem;
    href?: string;
    active?: boolean;
};

const harness = hookHarness();
const patchCalls: Array<{ url: string; data: unknown; options: unknown }> = [];
let sharedUnreadCount = 0;

vi.doMock('react', () => harness.react);

vi.doMock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) =>
        React.createElement('title', null, title),
    Link: ({ href, children, ...props }: GenericProps) =>
        React.createElement('a', { href, ...props }, children),
    router: {
        patch: (url: string, data: unknown, options: unknown) => {
            patchCalls.push({ url, data, options });
        },
    },
    usePage: () => ({
        props: {
            notifications: {
                unreadCount: sharedUnreadCount,
            },
        },
    }),
}));

vi.doMock('@/layouts/app-layout', () => ({
    default: ({ children }: { children?: ReactNode }) =>
        React.createElement('div', { 'data-layout': 'app' }, children),
}));

vi.doMock('@/routes/notifications', () => ({
    index: Object.assign(() => '/notifications', {
        url: () => '/notifications',
    }),
    markAllRead: Object.assign(
        () => ({ url: '/notifications/mark-all-as-read' }),
        {
            url: () => '/notifications/mark-all-as-read',
        },
    ),
}));

vi.doMock('@/hooks/use-translation', () => ({
    useTranslation: () => ({
        __: (key: string) => key,
    }),
}));

const { default: NotificationsPage } =
    await import('../../resources/js/pages/notifications/index');

function makeNotification(
    id: string,
    readAt: string | null = null,
): NotificationItem {
    return {
        id,
        type: 'system',
        title: 'Password changed',
        body: 'Your password was updated',
        icon: null,
        action: null,
        read_at: readAt,
        created_at: '2026-01-01T00:00:00.000000Z',
    };
}

describe('NotificationsPage', () => {
    beforeEach(() => {
        harness.unmount();
        patchCalls.length = 0;
        sharedUnreadCount = 0;
    });

    afterEach(() => {
        harness.unmount();
    });

    it('renders empty state when notifications.data is empty', () => {
        const paginated: PaginatedNotifications = {
            data: [],
            links: [],
            current_page: 1,
            last_page: 1,
            total: 0,
            per_page: 15,
        };

        const tree = harness.render(NotificationsPage, {
            notifications: paginated,
        });

        const items = findAll(
            tree,
            (props: GenericProps) => 'notification' in props,
        );
        assert.equal(items.length, 0);

        // Mark all as read button should not be present when unreadCount is 0
        const markAllBtn = find(
            tree,
            (props: GenericProps) =>
                Array.isArray(props.children) &&
                props.children.includes('Mark all as read'),
        );
        assert.equal(markAllBtn, null);
    });

    it('renders notification items and mark all as read button when unread count > 0', () => {
        sharedUnreadCount = 3;
        const paginated: PaginatedNotifications = {
            data: [
                makeNotification('n-1'),
                makeNotification('n-2', '2026-01-02T00:00:00.000000Z'),
            ],
            links: [],
            current_page: 1,
            last_page: 1,
            total: 2,
            per_page: 15,
        };

        const tree = harness.render(NotificationsPage, {
            notifications: paginated,
        });

        const items = findAll(
            tree,
            (props: GenericProps) => 'notification' in props,
        );
        assert.equal(items.length, 2);

        const markAllBtn = find(
            tree,
            (props: GenericProps) =>
                Array.isArray(props.children) &&
                props.children.includes('Mark all as read'),
        );
        assert.notEqual(markAllBtn, null);

        // Clicking Mark all as read dispatches patch to markAllRead endpoint with preserveScroll
        const click = markAllBtn?.props.onClick;
        assert.equal(typeof click, 'function');
        (click as () => void)();

        assert.equal(patchCalls.length, 1);
        assert.equal(patchCalls[0].url, '/notifications/mark-all-as-read');
        assert.deepEqual(patchCalls[0].options, { preserveScroll: true });
    });

    it('renders pagination links with active and previous/next labels', () => {
        const paginated: PaginatedNotifications = {
            data: [makeNotification('n-1')],
            links: [
                { url: null, label: '&laquo; Previous', active: false },
                { url: '/notifications?page=1', label: '1', active: true },
                { url: '/notifications?page=2', label: '2', active: false },
                {
                    url: '/notifications?page=2',
                    label: 'Next &raquo;',
                    active: false,
                },
            ],
            current_page: 1,
            last_page: 2,
            total: 20,
            per_page: 15,
        };

        const tree = harness.render(NotificationsPage, {
            notifications: paginated,
        });

        const links = findAll(
            tree,
            (props: GenericProps) => typeof props.href === 'string',
        );
        assert.equal(links.length, 3);
        assert.equal(links[0].props.href, '/notifications?page=1');
        assert.equal(links[1].props.href, '/notifications?page=2');
        assert.equal(links[2].props.href, '/notifications?page=2');
    });
});
