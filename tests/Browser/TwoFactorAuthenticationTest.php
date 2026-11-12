<?php

declare(strict_types=1);

use App\Models\User;

it('loads setup once per open and starts one new attempt on retry', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $page = visit(route('two-factor.show'));
    $page->assertSee('Enable 2FA');
    $page->script(<<<'JS'
        window.setupRequests = { qr: 0, key: 0 };
        const originalFetch = window.fetch;
        window.fetch = async (input, options) => {
            const url = new URL(input instanceof Request ? input.url : input, location.href);
            const endpoint = url.pathname.endsWith('/two-factor-qr-code') ? 'qr'
                : url.pathname.endsWith('/two-factor-secret-key') ? 'key' : null;
            if (endpoint) {
                window.setupRequests[endpoint]++;
                return new Response('{}', { status: 500, headers: { 'Content-Type': 'application/json' } });
            }
            return originalFetch(input, options);
        };
        JS);

    $page->click('Enable 2FA')->assertSee('Failed to fetch QR code');

    // The error state re-renders the hook and changes its translated callbacks.
    // Let several frames pass so an unintended automatic retry can run.
    $page->script('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    expect($page->script('window.setupRequests'))->toBe(['qr' => 1, 'key' => 1]);

    $page->click('Retry')->assertSee('Failed to fetch QR code');
    $page->script('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    expect($page->script('window.setupRequests'))->toBe(['qr' => 2, 'key' => 2]);

    $page->click('Close')->click('Enable 2FA')->assertSee('Failed to fetch QR code');
    $page->script('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    expect($page->script('window.setupRequests'))->toBe(['qr' => 3, 'key' => 3]);

    $page->assertNoJavaScriptErrors();
});

it('requests empty recovery codes once until the user acts', function (int $status): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    // Install the fetch stub before Inertia mounts the recovery-code component.
    $page = visit(route('user-profile.edit'));
    $page->assertSee('Profile');
    $page->script(<<<JS
        window.recoveryRequests = 0;
        const originalFetch = window.fetch;
        window.fetch = async (input, options) => {
            const url = new URL(input instanceof Request ? input.url : input, location.href);
            if (url.pathname.endsWith('/two-factor-recovery-codes')) {
                window.recoveryRequests++;
                return new Response('$status' === '200' ? '[]' : '{"message":"Server English message"}', {
                    status: $status, headers: { 'Content-Type': 'application/json' }
                });
            }
            return originalFetch(input, options);
        };
        JS);
    $page->click('Two-Factor Auth')->assertSee('View recovery codes');
    $page->script('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    expect($page->script('window.recoveryRequests'))->toBe(1);

    if ($status === 423) {
        $page->click('View recovery codes')->assertSee('Password confirmation required.')
            ->assertSee('Confirm password')->assertDontSee('Server English message');
    } else {
        $page->click('View recovery codes')->assertSee('Hide recovery codes');
    }

    $page->script('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    expect($page->script('window.recoveryRequests'))->toBe(2);

    $page->click('Hide recovery codes')->assertSee('View recovery codes');
    $page->script('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    expect($page->script('window.recoveryRequests'))->toBe(2);

    $page->assertNoJavaScriptErrors();
})->with([200, 423]);
