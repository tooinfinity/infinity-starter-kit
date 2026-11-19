/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { find, hookHarness } from './HookHarness';

const harness = hookHarness();
const routerReload = vi.fn();
const routerPatch = vi.fn();
let pageProps: Record<string, unknown> = {};

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    router: {
        reload: routerReload,
        patch: routerPatch,
    },
    Link: ({ children, href }: { children?: unknown; href?: string }) => ({
        type: 'a',
        props: { href, children },
    }),
}));

const { NotificationBell } =
    await import('../../resources/js/components/notifications/notification-bell');
const { NotificationDropdown } =
    await import(
        '../../resources/js/components/notifications/notification-dropdown'
    );

beforeEach(() => {
    vi.useFakeTimers();
    routerReload.mockClear();
    routerPatch.mockClear();
    pageProps = {
        auth: { user: { id: 1, name: 'Alice' } },
        notifications: {
            unreadCount: 2,
            recent: [
                {
                    id: 'notif-1',
                    type: 'user.activated',
                    title: 'Account Activated',
                    message: 'Your account was activated.',
                    read_at: null,
                    created_at: '2026-11-19T10:00:00Z',
                },
                {
                    id: 'notif-2',
                    type: 'password.changed',
                    title: 'Password Changed',
                    message: 'Your password was changed.',
                    read_at: '2026-11-19T10:05:00Z',
                    created_at: '2026-11-19T10:05:00Z',
                },
            ],
        },
    };
});

afterEach(() => {
    harness.unmount();
    vi.useRealTimers();
});

test('regression #46 / #28: rendering notification bell or dropdown does not trigger reload on navigation', () => {
    // Initial mount / render during page navigation
    harness.render(NotificationBell, {});
    assert.equal(routerReload.mock.calls.length, 0);

    // Simulate subsequent page navigations / component re-renders
    for (let i = 0; i < 3; i++) {
        harness.rerender();
    }
    assert.equal(routerReload.mock.calls.length, 0);

    // Rendering dropdown directly also does not trigger reload
    harness.render(NotificationDropdown, {});
    assert.equal(routerReload.mock.calls.length, 0);
});

test('opening notification bell triggers partial reload of recent notifications strictly on open', () => {
    const bellTree = harness.render(NotificationBell, {});
    assert.ok(bellTree);

    // Find DropdownMenu and trigger onOpenChange(true)
    const dropdownMenu = find(
        bellTree,
        (props) => typeof props.onOpenChange === 'function',
    );
    assert.ok(dropdownMenu);

    const onOpenChange = dropdownMenu.props.onOpenChange as (
        open: boolean,
    ) => void;

    // Open dropdown
    onOpenChange(true);
    assert.equal(routerReload.mock.calls.length, 1);
    assert.deepEqual(routerReload.mock.calls[0]?.[0], {
        only: ['notifications.recent'],
    });

    // Close dropdown -> does NOT reload
    onOpenChange(false);
    assert.equal(routerReload.mock.calls.length, 1);
});

test('renders empty state when recent notifications list is empty', () => {
    pageProps.notifications = {
        unreadCount: 0,
        recent: [],
    };

    const tree = harness.render(NotificationDropdown, {});
    assert.ok(tree);

    const emptyState = find(
        tree,
        (props) => props.title === 'No notifications',
    );
    assert.ok(emptyState);
    assert.equal(
        emptyState.props.description,
        'You are caught up with all notifications.',
    );
});

test('mark all as read button triggers patch request', () => {
    const tree = harness.render(NotificationDropdown, {});
    assert.ok(tree);

    const markAllButton = find(
        tree,
        (props) =>
            props.variant === 'ghost' &&
            typeof props.onClick === 'function',
    );

    assert.ok(markAllButton);
    const onClick = markAllButton.props.onClick as (e: unknown) => void;
    onClick({ preventDefault: () => {} });

    assert.equal(routerPatch.mock.calls.length, 1);
});
