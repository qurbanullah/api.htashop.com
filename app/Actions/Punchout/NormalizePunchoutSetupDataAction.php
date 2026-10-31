<?php

namespace App\Actions\Punchout;

use App\Support\Punchout\Data\PunchoutSetupData;
use InvalidArgumentException;
use SimpleXMLElement;

class NormalizePunchoutSetupDataAction
{
    public function handle(string $protocol, array $input, ?string $rawBody = null): PunchoutSetupData
    {
        return match ($protocol) {
            'cxml' => $this->normalizeCxml($rawBody),
            'oci' => $this->normalizeOci($input),
            default => throw new InvalidArgumentException('Unsupported punchout setup protocol.'),
        };
    }

    private function normalizeCxml(?string $rawBody): PunchoutSetupData
    {
        if (! $rawBody) {
            throw new InvalidArgumentException('A cXML setup request body is required.');
        }

        $xml = @simplexml_load_string($rawBody);

        if (! $xml) {
            throw new InvalidArgumentException('The cXML setup request body is invalid.');
        }

        $setupRequest = $xml->Request->PunchOutSetupRequest ?? null;
        $returnUrl = trim((string) ($setupRequest?->BrowserFormPost?->URL ?? ''));

        if ($returnUrl === '') {
            throw new InvalidArgumentException('The cXML setup request is missing BrowserFormPost URL.');
        }

        return new PunchoutSetupData(
            protocol: 'cxml',
            operation: 'PunchOutSetupRequest',
            returnUrl: $returnUrl,
            buyerCookie: $this->nullableString($setupRequest?->BuyerCookie ?? null),
            payload: [
                'from' => [
                    // Ariba puts From/To in the cXML Header; some buyers nest them
                    // inside PunchOutSetupRequest. Accept either.
                    'credential' => $this->cxmlIdentity(
                        $setupRequest?->From?->Credential?->Identity,
                        $xml->Header?->From?->Credential?->Identity,
                    ),
                ],
                'to' => [
                    'credential' => $this->cxmlIdentity(
                        $setupRequest?->To?->Credential?->Identity,
                        $xml->Header?->To?->Credential?->Identity,
                    ),
                ],
                'sender' => [
                    'identity' => $this->nullableString($xml->Header?->Sender?->Credential?->Identity ?? null),
                    'shared_secret' => $this->nullableString($xml->Header?->Sender?->Credential?->SharedSecret ?? null),
                ],
                'browser_form_post_url' => $returnUrl,
            ],
        );
    }

    private function normalizeOci(array $input): PunchoutSetupData
    {
        $returnUrl = trim((string) data_get($input, 'HOOK_URL', data_get($input, 'return_url', '')));

        if ($returnUrl === '') {
            throw new InvalidArgumentException('The OCI setup request is missing HOOK_URL.');
        }

        return new PunchoutSetupData(
            protocol: 'oci',
            operation: 'OCI_LOGIN',
            returnUrl: $returnUrl,
            buyerCookie: $this->nullableString(data_get($input, 'BUYER_COOKIE', data_get($input, 'buyer_cookie'))),
            payload: [
                'username' => data_get($input, 'USERNAME', data_get($input, 'username')),
                'password' => data_get($input, 'PASSWORD', data_get($input, 'password')),
                'hook_url' => $returnUrl,
            ],
        );
    }

    /**
     * First non-empty identity among the candidate nodes. Ariba sends From/To in
     * the cXML Header, while other buyers nest them in PunchOutSetupRequest.
     */
    private function cxmlIdentity(?SimpleXMLElement ...$nodes): ?string
    {
        foreach ($nodes as $node) {
            $identity = $this->nullableString($node);

            if ($identity !== null) {
                return $identity;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }
}
