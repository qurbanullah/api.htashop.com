<?php

namespace App\Actions\Punchout;

use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutSetupData;
use Illuminate\Validation\ValidationException;

class ValidatePunchoutSetupCredentialsAction
{
    public function handle(Tenant $tenant, PunchoutSetupData $setupData, array $configuration = []): void
    {
        $credentialConfiguration = (array) data_get($configuration, 'credentials', []);

        if ($credentialConfiguration === []) {
            return;
        }

        match ($setupData->protocol) {
            'cxml' => $this->validateCxml($setupData, $credentialConfiguration),
            'oci' => $this->validateOci($setupData, $credentialConfiguration),
            default => null,
        };
    }

    private function validateCxml(PunchoutSetupData $setupData, array $configuration): void
    {
        $senderIdentity = data_get($setupData->payload, 'sender.identity');
        $senderSharedSecret = data_get($setupData->payload, 'sender.shared_secret');
        $fromIdentity = data_get($setupData->payload, 'from.credential');
        $toIdentity = data_get($setupData->payload, 'to.credential');

        $this->assertConfiguredCredentialMatches(
            actual: $senderIdentity,
            expected: data_get($configuration, 'sender_identity'),
            field: 'credentials',
            message: 'Invalid punchout sender identity.'
        );

        $this->assertConfiguredCredentialMatches(
            actual: $senderSharedSecret,
            expected: data_get($configuration, 'shared_secret'),
            field: 'credentials',
            message: 'Invalid punchout shared secret.'
        );

        $this->assertConfiguredCredentialMatches(
            actual: $fromIdentity,
            expected: data_get($configuration, 'from_identity'),
            field: 'credentials',
            message: 'Invalid punchout from identity.'
        );

        $this->assertConfiguredCredentialMatches(
            actual: $toIdentity,
            expected: data_get($configuration, 'to_identity'),
            field: 'credentials',
            message: 'Invalid punchout to identity.'
        );
    }

    private function validateOci(PunchoutSetupData $setupData, array $configuration): void
    {
        $this->assertConfiguredCredentialMatches(
            actual: data_get($setupData->payload, 'username'),
            expected: data_get($configuration, 'username'),
            field: 'credentials',
            message: 'Invalid OCI username.'
        );

        $this->assertConfiguredCredentialMatches(
            actual: data_get($setupData->payload, 'password'),
            expected: data_get($configuration, 'password'),
            field: 'credentials',
            message: 'Invalid OCI password.'
        );
    }

    private function assertConfiguredCredentialMatches(mixed $actual, mixed $expected, string $field, string $message): void
    {
        if ($expected === null || $expected === '') {
            return;
        }

        $actualStr = (string) ($actual ?? '');

        if (is_array($expected)) {
            $normalizedExpected = array_values(array_filter($expected, fn (mixed $value) => $value !== null && $value !== ''));

            if ($normalizedExpected === []) {
                return;
            }

            // Use hash_equals for every comparison to prevent timing attacks.
            foreach ($normalizedExpected as $candidate) {
                if (hash_equals((string) $candidate, $actualStr)) {
                    return;
                }
            }

            throw ValidationException::withMessages([$field => [$message]]);
        }

        if (! hash_equals((string) $expected, $actualStr)) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }
}
