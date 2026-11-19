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

const { useTranslation } =
    await import('../../resources/js/hooks/use-translation');

const holder: { current?: ReturnType<typeof useTranslation> } = {};

function Probe() {
    const value = useTranslation();
    harness.react.useEffect(() => {
        holder.current = value;
    });
    return null;
}

const currentTranslation = () => {
    assert.ok(holder.current);
    return holder.current;
};

beforeEach(() => {
    pageProps = {
        locale: 'en',
        lang: {
            Welcome: 'Welcome',
            'Hello, :name!': 'Hello, :name!',
            'Hello, {name}!': 'Hello, {name}!',
            auth: {
                failed: 'These credentials do not match our records.',
                throttle: 'Too many login attempts. Please try again in :seconds seconds.',
            },
            apples: '{0} There are none|{1} There is one|[2,*] There are many',
            simple_apples: 'one apple|many apples',
        },
    };
    harness.render(Probe, {});
});

afterEach(() => {
    harness.unmount();
});

test('returns translated string when exact key exists in lang', () => {
    assert.equal(currentTranslation().__('Welcome'), 'Welcome');
});

test('resolves nested dot-notation keys in lang dictionary', () => {
    assert.equal(
        currentTranslation().__('auth.failed'),
        'These credentials do not match our records.',
    );
    assert.equal(
        currentTranslation().__('auth.throttle', { seconds: 30 }),
        'Too many login attempts. Please try again in 30 seconds.',
    );
});

test('falls back to the key itself when key is not present in lang', () => {
    assert.equal(currentTranslation().__('Nonexistent string'), 'Nonexistent string');
    assert.equal(
        currentTranslation().__('missing.nested.key'),
        'missing.nested.key',
    );
});

test('falls back to the key when lang prop is missing or invalid', () => {
    pageProps = {};
    harness.render(Probe, {});
    assert.equal(currentTranslation().__('Save'), 'Save');
    assert.equal(
        currentTranslation().__('Save :item', { item: 'document' }),
        'Save document',
    );
});

test('replaces placeholders with colon and brace formats', () => {
    assert.equal(
        currentTranslation().__('Hello, :name!', { name: 'Alice' }),
        'Hello, Alice!',
    );
    assert.equal(
        currentTranslation().__('Hello, {name}!', { name: 'Bob' }),
        'Hello, Bob!',
    );
});

test('appends string replacement directly to template', () => {
    assert.equal(currentTranslation().__('Hello', 'World'), 'Hello World');
});

test('preserves placeholders if not provided in replacements object', () => {
    assert.equal(currentTranslation().__('Hello, :name!'), 'Hello, :name!');
    assert.equal(
        currentTranslation().__('Hello, :name and :other!', { name: 'Alice' }),
        'Hello, Alice and :other!',
    );
});

test('handles pluralization via transChoice with exact and range rules', () => {
    assert.equal(currentTranslation().transChoice('apples', 0), 'There are none');
    assert.equal(currentTranslation().transChoice('apples', 1), 'There is one');
    assert.equal(currentTranslation().transChoice('apples', 2), 'There are many');
    assert.equal(currentTranslation().transChoice('apples', 10), 'There are many');
});

test('handles simple two-part pluralization without explicit counts', () => {
    assert.equal(
        currentTranslation().transChoice('simple_apples', 1),
        'one apple',
    );
    assert.equal(
        currentTranslation().transChoice('simple_apples', 2),
        'many apples',
    );
});

test('trans and trans_choice aliases delegate properly', () => {
    assert.equal(currentTranslation().trans('Welcome'), 'Welcome');
    assert.equal(currentTranslation().trans_choice('apples', 1), 'There is one');
});
