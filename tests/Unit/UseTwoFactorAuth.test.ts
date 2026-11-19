/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { hookHarness } from './HookHarness';

const harness = hookHarness();
const translations: Record<string, string> = {
    'Failed to fetch QR code': 'Échec de la récupération du code QR',
    'Failed to fetch a setup key': 'Échec de la récupération de la clé de configuration',
    'Failed to fetch recovery codes': 'Échec de la récupération des codes de récupération',
    'Password confirmation required.': 'Confirmation du mot de passe requise.',
};

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            lang: translations,
        },
    }),
}));

const { useTwoFactorAuth } =
    await import('../../resources/js/hooks/use-two-factor-auth');

const holder: { current?: ReturnType<typeof useTwoFactorAuth> } = {};

function Probe() {
    const value = useTwoFactorAuth();
    harness.react.useEffect(() => {
        holder.current = value;
    });
    return null;
}

const hookResult = () => {
    assert.ok(holder.current);
    return holder.current;
};

beforeEach(() => {
    harness.render(Probe, {});
});

afterEach(() => {
    harness.unmount();
    vi.unstubAllGlobals();
});

test('successful fetchSetupData populates qrCodeSvg and manualSetupKey and sets hasSetupData to true', async () => {
    vi.stubGlobal('fetch', async (url: string) => {
        if (url.includes('/user/two-factor-qr-code')) {
            return Response.json({ svg: '<svg>qr</svg>' });
        }
        if (url.includes('/user/two-factor-secret-key')) {
            return Response.json({ secretKey: 'SECRET123' });
        }
        return new Response(null, { status: 404 });
    });

    assert.equal(hookResult().hasSetupData, false);
    assert.equal(hookResult().qrCodeSvg, null);
    assert.equal(hookResult().manualSetupKey, null);

    await hookResult().fetchSetupData();
    harness.rerender();

    assert.equal(hookResult().hasSetupData, true);
    assert.equal(hookResult().qrCodeSvg, '<svg>qr</svg>');
    assert.equal(hookResult().manualSetupKey, 'SECRET123');
    assert.deepEqual(hookResult().errors, []);
    assert.equal(hookResult().requiresPasswordConfirmation, false);
});

test('clearSetupData resets setup data, errors, and hasSetupData flag', async () => {
    vi.stubGlobal('fetch', async (url: string) => {
        if (url.includes('/user/two-factor-qr-code')) {
            return Response.json({ svg: '<svg>qr</svg>' });
        }
        if (url.includes('/user/two-factor-secret-key')) {
            return Response.json({ secretKey: 'SECRET123' });
        }
        return new Response(null, { status: 404 });
    });

    await hookResult().fetchSetupData();
    harness.rerender();
    assert.equal(hookResult().hasSetupData, true);

    hookResult().clearSetupData();
    harness.rerender();

    assert.equal(hookResult().hasSetupData, false);
    assert.equal(hookResult().qrCodeSvg, null);
    assert.equal(hookResult().manualSetupKey, null);
    assert.deepEqual(hookResult().errors, []);
});

test('regression #131 / #144: server English error messages are localized through translation instead of returned raw', async () => {
    vi.stubGlobal('fetch', async () => {
        return new Response(
            JSON.stringify({ message: 'Hardcoded English server error' }),
            {
                status: 500,
                headers: { 'Content-Type': 'application/json' },
            },
        );
    });

    await hookResult().fetchSetupData();
    harness.rerender();

    assert.equal(hookResult().hasSetupData, false);
    assert.ok(hookResult().errors.length > 0);
    assert.equal(
        hookResult().errors.includes('Hardcoded English server error'),
        false,
    );
    assert.ok(
        hookResult().errors.includes(
            'Échec de la récupération du code QR',
        ),
    );
    assert.ok(
        hookResult().errors.includes(
            'Échec de la récupération de la clé de configuration',
        ),
    );
});

test('HTTP 423 response on fetchSetupData triggers password confirmation required state and localized error', async () => {
    vi.stubGlobal('fetch', async () => {
        return new Response(
            JSON.stringify({ message: 'Password confirmation required.' }),
            {
                status: 423,
                headers: { 'Content-Type': 'application/json' },
            },
        );
    });

    await hookResult().fetchSetupData();
    harness.rerender();

    assert.equal(hookResult().requiresPasswordConfirmation, true);
    assert.deepEqual(hookResult().errors, [
        'Confirmation du mot de passe requise.',
    ]);
    assert.equal(hookResult().qrCodeSvg, null);
    assert.equal(hookResult().manualSetupKey, null);
});

test('fetchRecoveryCodes populates recoveryCodesList on success and handles 423 / error correctly', async () => {
    vi.stubGlobal('fetch', async (url: string) => {
        if (url.includes('/user/two-factor-recovery-codes')) {
            return Response.json(['code-1', 'code-2', 'code-3']);
        }
        return new Response(null, { status: 404 });
    });

    await hookResult().fetchRecoveryCodes();
    harness.rerender();
    assert.deepEqual(hookResult().recoveryCodesList, [
        'code-1',
        'code-2',
        'code-3',
    ]);
    assert.deepEqual(hookResult().errors, []);

    vi.stubGlobal('fetch', async () => {
        return new Response(null, { status: 423 });
    });

    await hookResult().fetchRecoveryCodes();
    harness.rerender();
    assert.equal(hookResult().requiresPasswordConfirmation, true);
    assert.deepEqual(hookResult().errors, [
        'Confirmation du mot de passe requise.',
    ]);
    assert.deepEqual(hookResult().recoveryCodesList, []);
});

test('no retry loop on failure: setup attempts abort superseded requests without recursive calls', async () => {
    let fetchCount = 0;
    vi.stubGlobal('fetch', async () => {
        fetchCount++;
        return new Response(null, { status: 500 });
    });

    await hookResult().fetchSetupData();
    harness.rerender();

    assert.equal(fetchCount, 2); // 1 for qrCode + 1 for secretKey
    assert.ok(hookResult().errors.length > 0);
});
