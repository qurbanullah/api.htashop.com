<?php

use App\Models\User;

/**
 * The storefront, admin and manage clients share one parser that reads extras
 * straight off the envelope: `errors` for per-field messages,
 * `requires_email_verification` and `email` for the verify-email redirect.
 *
 * Regressions here are silent and expensive — every form degrades to a generic
 * banner and the verify-email redirect stops firing.
 */
it('exposes per-field validation errors at the top level', function () {
    $this->postJson('/api/v1/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['email', 'password']]);
});

it('keeps the validation message alongside the field errors', function () {
    $this->postJson('/api/v1/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'The email field is required.');
});

it('lifts the verify-email flags out of the data payload', function () {
    $user = User::factory()->unverified()->create();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('requires_email_verification', true)
        ->assertJsonPath('email', $user->email)
        // The nested copy stays for callers that already read it from there.
        ->assertJsonPath('data.requires_email_verification', true);
});

it('still answers unknown API routes with the flat envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('status_code', 404)
        ->assertJsonStructure(['success', 'message', 'status_code']);
});
