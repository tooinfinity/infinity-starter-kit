/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { find, hookHarness } from './HookHarness';

const harness = hookHarness();
const routerVisit = vi.fn();

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({ props: { lang: {} } }),
    router: { visit: routerVisit },
    Form: ({ children }: { children?: unknown }) =>
        typeof children === 'function'
            ? children({ processing: false })
            : children,
}));

const { default: TwoFactorSetupModal } =
    await import('../../resources/js/components/two-factor-setup-modal');

let fetchSetupDataCount = 0;
let fetchSetupDataMock = vi.fn(async () => {
    fetchSetupDataCount++;
});

const defaultProps = {
    isOpen: true,
    onClose: vi.fn(),
    requiresConfirmation: false,
    requiresPasswordConfirmation: false,
    twoFactorEnabled: false,
    qrCodeSvg: null,
    manualSetupKey: null,
    clearSetupData: vi.fn(),
    fetchSetupData: fetchSetupDataMock,
    errors: [] as string[],
};

beforeEach(() => {
    fetchSetupDataCount = 0;
    fetchSetupDataMock = vi.fn(async () => {
        fetchSetupDataCount++;
    });
    routerVisit.mockClear();
});

afterEach(() => {
    harness.unmount();
});

test('request happens once when modal opens without setup data', () => {
    harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        fetchSetupData: fetchSetupDataMock,
    });

    assert.equal(fetchSetupDataCount, 1);
});

test('does not request setup data when modal is closed', () => {
    harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        isOpen: false,
        fetchSetupData: fetchSetupDataMock,
    });

    assert.equal(fetchSetupDataCount, 0);
});

test('regression #129: does not re-request setup data in an endless loop when fetch fails and data remains null', () => {
    const failingFetch = vi.fn(async () => {
        fetchSetupDataCount++;
        // Simulates a failed fetch that resolves without setting data
    });

    // Initial open with failing request
    harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        fetchSetupData: failingFetch,
        errors: ['Failed to fetch QR code'],
    });

    assert.equal(fetchSetupDataCount, 1);

    // Simulate multiple re-renders with failed state (error displayed, qrCodeSvg still null)
    for (let i = 0; i < 5; i++) {
        harness.rerender();
    }

    // Must still be exactly 1 call, preventing the infinite loop regression
    assert.equal(fetchSetupDataCount, 1);
});

test('resets session and requests once more when closed and reopened', () => {
    harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        isOpen: true,
        fetchSetupData: fetchSetupDataMock,
    });

    assert.equal(fetchSetupDataCount, 1);

    // Close modal
    harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        isOpen: false,
        fetchSetupData: fetchSetupDataMock,
    });

    assert.equal(fetchSetupDataCount, 1);

    // Reopen modal
    harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        isOpen: true,
        fetchSetupData: fetchSetupDataMock,
    });

    assert.equal(fetchSetupDataCount, 2);
});

test('renders error state and provides retry action when errors are present', async () => {
    let resolveInitialFetch: () => void = () => {};
    const initialFetchPromise = new Promise<void>((resolve) => {
        resolveInitialFetch = resolve;
    });

    let fetchCount = 0;
    const fetchMock = vi.fn(async () => {
        fetchCount++;
        if (fetchCount === 1) {
            await initialFetchPromise;
        }
    });

    harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        errors: ['Failed to fetch QR code'],
        fetchSetupData: fetchMock,
    });

    assert.equal(fetchCount, 1);

    // Wait for the initial request to settle and microtasks to complete
    resolveInitialFetch();
    await initialFetchPromise;
    await Promise.resolve();
    await Promise.resolve();

    const settledTree = harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        errors: ['Failed to fetch QR code'],
        fetchSetupData: fetchMock,
    });

    assert.ok(settledTree);

    const step = find(
        settledTree,
        (props) => 'errors' in props && 'onRetry' in props,
    );

    assert.ok(step);
    assert.deepEqual(step.props.errors, ['Failed to fetch QR code']);
    assert.equal(typeof step.props.onRetry, 'function');

    // Trigger retry
    await (step.props.onRetry as () => Promise<void>)();

    assert.equal(fetchCount, 2);
});

test('passes requiresPasswordConfirmation when password confirmation is required', () => {
    const tree = harness.render(TwoFactorSetupModal, {
        ...defaultProps,
        requiresPasswordConfirmation: true,
        errors: ['Password confirmation required.'],
        fetchSetupData: fetchSetupDataMock,
    });

    assert.ok(tree);

    const step = find(
        tree,
        (props) => 'requiresPasswordConfirmation' in props,
    );

    assert.ok(step);
    assert.equal(step.props.requiresPasswordConfirmation, true);
    assert.deepEqual(step.props.errors, ['Password confirmation required.']);
});
