/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, it, vi } from 'vite-plus/test';
import { find, hookHarness } from './HookHarness';

type GenericProps = Record<string, unknown> & {
    children?: ReactNode;
    onClick?: () => void;
    isOpen?: boolean;
    disabled?: boolean;
    type?: string;
    title?: string;
    recoveryCodesList?: string[];
    onConfirmPassword?: () => void;
};

const harness = hookHarness();
const visitCalls: string[] = [];

let hookState = {
    qrCodeSvg: '<svg></svg>',
    hasSetupData: false,
    manualSetupKey: 'TESTKEY',
    clearSetupData: vi.fn(),
    fetchSetupData: vi.fn(),
    recoveryCodesList: [] as string[],
    fetchRecoveryCodes: vi.fn(),
    errors: {} as Record<string, string>,
    requiresPasswordConfirmation: false,
};

vi.doMock('react', () => harness.react);

vi.doMock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) =>
        React.createElement('title', null, title),
    Form: ({
        children,
    }: {
        children?: ((state: { processing: boolean }) => ReactNode) | ReactNode;
    }) =>
        React.createElement(
            'form',
            null,
            typeof children === 'function'
                ? children({ processing: false })
                : children,
        ),
    router: {
        visit: (url: string) => {
            visitCalls.push(url);
        },
    },
}));

vi.doMock('@/layouts/app-layout', () => ({
    default: ({ children }: { children?: ReactNode }) =>
        React.createElement('div', { 'data-layout': 'app' }, children),
}));

vi.doMock('@/layouts/settings/layout', () => ({
    default: ({ children }: { children?: ReactNode }) =>
        React.createElement('div', { 'data-layout': 'settings' }, children),
}));

vi.doMock('@/routes/two-factor', () => ({
    show: Object.assign(() => '/settings/two-factor', {
        url: () => '/settings/two-factor',
    }),
    enable: {
        form: () => ({
            action: '/user/two-factor-authentication',
            method: 'post',
        }),
    },
    disable: {
        form: () => ({
            action: '/user/two-factor-authentication',
            method: 'delete',
        }),
    },
}));

vi.doMock('@/hooks/use-translation', () => ({
    useTranslation: () => ({
        __: (key: string) => key,
    }),
}));

vi.doMock('@/hooks/use-two-factor-auth', () => ({
    useTwoFactorAuth: () => hookState,
}));

const { default: TwoFactorPage } =
    await import('../../resources/js/pages/user-two-factor-authentication/show');

describe('TwoFactorPage', () => {
    beforeEach(() => {
        harness.unmount();
        visitCalls.length = 0;
        hookState = {
            qrCodeSvg: '<svg></svg>',
            hasSetupData: false,
            manualSetupKey: 'TESTKEY',
            clearSetupData: vi.fn(),
            fetchSetupData: vi.fn(),
            recoveryCodesList: [],
            fetchRecoveryCodes: vi.fn(),
            errors: {},
            requiresPasswordConfirmation: false,
        };
    });

    afterEach(() => {
        harness.unmount();
    });

    it('does not render controls when canManageTwoFactor is false', () => {
        const tree = harness.render(TwoFactorPage, {
            canManageTwoFactor: false,
            twoFactorEnabled: false,
        });

        const heading = find(
            tree,
            (props: GenericProps) =>
                props.title === 'Two-factor authentication',
        );
        assert.equal(heading, null);
    });

    it('renders Enable 2FA button when twoFactorEnabled is false and hasSetupData is false', () => {
        const tree = harness.render(TwoFactorPage, {
            canManageTwoFactor: true,
            twoFactorEnabled: false,
        });

        const enableBtn = find(
            tree,
            (props: GenericProps) =>
                props.children === 'Enable 2FA' && props.type === 'submit',
        );
        assert.notEqual(enableBtn, null);

        const modal = find(
            tree,
            (props: GenericProps) => typeof props.isOpen === 'boolean',
        );
        assert.equal(modal?.props.isOpen, false);
    });

    it('renders Continue setup button when hasSetupData is true and twoFactorEnabled is false', () => {
        hookState.hasSetupData = true;

        let tree = harness.render(TwoFactorPage, {
            canManageTwoFactor: true,
            twoFactorEnabled: false,
        });

        const continueBtn = find(
            tree,
            (props: GenericProps) =>
                Array.isArray(props.children) &&
                props.children.includes('Continue setup'),
        );
        assert.notEqual(continueBtn, null);

        const click = continueBtn?.props.onClick;
        assert.equal(typeof click, 'function');
        (click as () => void)();

        tree = harness.render(TwoFactorPage, {
            canManageTwoFactor: true,
            twoFactorEnabled: false,
        });

        const modal = find(
            tree,
            (props: GenericProps) => typeof props.isOpen === 'boolean',
        );
        assert.equal(modal?.props.isOpen, true);
    });

    it('renders Disable 2FA button and recovery codes section when twoFactorEnabled is true', () => {
        const tree = harness.render(TwoFactorPage, {
            canManageTwoFactor: true,
            twoFactorEnabled: true,
        });

        const disableBtn = find(
            tree,
            (props: GenericProps) =>
                props.children === 'Disable 2FA' && props.type === 'submit',
        );
        assert.notEqual(disableBtn, null);

        const recoverySection = find(
            tree,
            (props: GenericProps) =>
                'recoveryCodesList' in props &&
                typeof props.onConfirmPassword === 'function',
        );
        assert.notEqual(recoverySection, null);

        const onConfirm = (recoverySection?.props as GenericProps | undefined)
            ?.onConfirmPassword;
        assert.equal(typeof onConfirm, 'function');
        (onConfirm as () => void)();

        assert.equal(visitCalls.length, 1);
        assert.equal(visitCalls[0], '/settings/two-factor');
    });
});
