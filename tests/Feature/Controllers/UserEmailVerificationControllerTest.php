<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('may verify email', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'user.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->email)]
    );

    $response = $this->actingAs($user)
        ->fromRoute('user.verification.notice')
        ->get($verificationUrl);

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

it('redirects to dashboard if already verified', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'user.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->email)]
    );

    $response = $this->actingAs($user)
        ->fromRoute('user.verification.notice')
        ->get($verificationUrl);

    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

it('requires valid signature', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $invalidUrl = route('user.verification.verify', [
        'id' => $user->getKey(),
        'hash' => sha1((string) $user->email),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user.verification.notice')
        ->get($invalidUrl);

    $response->assertForbidden();
});

it('redirects unverified user away from verified routes to verification notice', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirectToRoute('verification.notice');
});

it('rejects expired signature on verification link', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $expiredUrl = URL::temporarySignedRoute(
        'user.verification.verify',
        now()->subMinutes(5),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->email)]
    );

    $response = $this->actingAs($user)->get($expiredUrl);

    $response->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects verification link when email hash is tampered or mismatched', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $validUrl = URL::temporarySignedRoute(
        'user.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->email)]
    );

    // Signature tampering: modifying the hash invalidates the URL signature
    $tamperedUrl = str_replace(sha1((string) $user->email), sha1('tampered@example.com'), $validUrl);

    $response = $this->actingAs($user)->get($tamperedUrl);

    $response->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    // Separately signed URL with incorrect hash: signature is valid, but email-hash authorization fails
    $mismatchedHashUrl = URL::temporarySignedRoute(
        'user.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1('mismatched@example.com')]
    );

    $mismatchedResponse = $this->actingAs($user)->get($mismatchedHashUrl);

    $mismatchedResponse->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
