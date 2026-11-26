/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { hookHarness } from './HookHarness';

const harness = hookHarness();
let pageProps: Record<string, unknown> = {};

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
}));

const { useAuthorization } =
    await import('../../resources/js/hooks/use-authorization');

const holder: { current?: ReturnType<typeof useAuthorization> } = {};

function Probe() {
    const value = useAuthorization();
    harness.react.useEffect(() => {
        holder.current = value;
    });
    return null;
}

const currentAuth = () => {
    assert.ok(holder.current);
    return holder.current;
};

beforeEach(() => {
    pageProps = {
        auth: {
            user: { id: 1, name: 'Admin User' },
            roles: ['admin', 'manager'],
            permissions: ['users.view', 'users.create', 'audit.view'],
        },
    };
    harness.render(Probe, {});
});

afterEach(() => {
    harness.unmount();
});

test('can checks single permission correctly', () => {
    assert.equal(currentAuth().can('users.view'), true);
    assert.equal(currentAuth().can('users.create'), true);
    assert.equal(currentAuth().can('users.delete'), false);
});

test('canAny checks whether user has at least one permission', () => {
    assert.equal(currentAuth().canAny(['users.delete', 'users.view']), true);
    assert.equal(currentAuth().canAny(['users.delete', 'users.update']), false);
    assert.equal(currentAuth().canAny([]), false);
});

test('canAll checks whether user has all requested permissions', () => {
    assert.equal(
        currentAuth().canAll(['users.view', 'audit.view']),
        true,
    );
    assert.equal(
        currentAuth().canAll(['users.view', 'users.delete']),
        false,
    );
    assert.equal(currentAuth().canAll([]), true);
});

test('hasRole checks assigned roles correctly', () => {
    assert.equal(currentAuth().hasRole('admin'), true);
    assert.equal(currentAuth().hasRole('manager'), true);
    assert.equal(currentAuth().hasRole('guest'), false);
});

test('gracefully handles missing auth or undefined permissions/roles', () => {
    pageProps = {};
    harness.render(Probe, {});

    assert.equal(currentAuth().can('users.view'), false);
    assert.equal(currentAuth().canAny(['users.view']), false);
    assert.equal(currentAuth().canAll(['users.view']), false);
    assert.equal(currentAuth().hasRole('admin'), false);
});

test('gracefully handles auth object with empty or null attributes', () => {
    pageProps = {
        auth: {
            user: null,
            permissions: null,
            roles: null,
        },
    };
    harness.render(Probe, {});

    assert.equal(currentAuth().can('users.view'), false);
    assert.equal(currentAuth().canAny(['users.view']), false);
    assert.equal(currentAuth().canAll(['users.view']), false);
    assert.equal(currentAuth().hasRole('admin'), false);
});
