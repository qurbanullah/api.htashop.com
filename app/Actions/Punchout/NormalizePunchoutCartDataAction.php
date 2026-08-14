<?php

namespace App\Actions\Punchout;

use App\Support\Punchout\Data\PunchoutCartData;
use InvalidArgumentException;

class NormalizePunchoutCartDataAction
{
    public function handle(string $protocol, array $input, ?string $rawBody = null): PunchoutCartData
    {
        return match ($protocol) {
            'cxml' => $this->normalizeCxml($input, $rawBody),
            'oci' => $this->normalizeOci($input),
            default => throw new InvalidArgumentException('Unsupported punchout cart protocol.'),
        };
    }

    private function normalizeCxml(array $input, ?string $rawBody): PunchoutCartData
    {
        $sessionUuid = $this->requiredString(data_get($input, 'session'), 'The cart request is missing punchout session identifier.');
        $sessionToken = $this->requiredString(data_get($input, 'token'), 'The cart request is missing punchout session token.');

        if ($rawBody) {
            $xml = @simplexml_load_string($rawBody);

            if ($xml && isset($xml->Request->PunchOutOrderMessage)) {
                $orderMessage = $xml->Request->PunchOutOrderMessage;

                $xmlItems = [];
                foreach ($orderMessage->ItemIn as $itemIn) {
                    $xmlItems[] = [
                        'supplier_part_id' => (string) ($itemIn->ItemID->SupplierPartID ?? ''),
                        'description' => (string) ($itemIn->ItemDetail->Description ?? ''),
                        'quantity' => (float) ($itemIn['quantity'] ?? 1),
                        'price' => (string) ($itemIn->ItemDetail->UnitPrice->Money ?? '0.00'),
                        'currency' => (string) ($itemIn->ItemDetail->UnitPrice->Money['currency'] ?? 'USD'),
                        'unit_of_measure' => (string) ($itemIn->ItemDetail->UnitOfMeasure ?? 'EA'),
                    ];
                }

                return new PunchoutCartData(
                    protocol: 'cxml',
                    operation: 'PunchOutOrderMessage',
                    sessionUuid: $sessionUuid,
                    sessionToken: $sessionToken,
                    returnUrl: trim((string) data_get($input, 'return_url', 'about:blank')),
                    buyerCookie: $this->nullableString($orderMessage->BuyerCookie ?? null),
                    items: $xmlItems,
                    payload: ['source' => 'xml'],
                );
            }
        }

        $returnUrl = trim((string) data_get($input, 'return_url', data_get($input, 'BrowserFormPost.URL', '')));

        if ($returnUrl === '') {
            throw new InvalidArgumentException('The cXML cart request is missing return_url.');
        }

        return new PunchoutCartData(
            protocol: 'cxml',
            operation: 'PunchOutOrderMessage',
            sessionUuid: $sessionUuid,
            sessionToken: $sessionToken,
            returnUrl: $returnUrl,
            buyerCookie: $this->nullableString(data_get($input, 'buyer_cookie')),
            items: $this->normalizeItems(data_get($input, 'items', [])),
            payload: ['source' => 'array'],
        );
    }

    private function normalizeOci(array $input): PunchoutCartData
    {
        $sessionUuid = $this->requiredString(data_get($input, 'session'), 'The cart request is missing punchout session identifier.');
        $sessionToken = $this->requiredString(data_get($input, 'token'), 'The cart request is missing punchout session token.');
        $returnUrl = trim((string) data_get($input, 'HOOK_URL', data_get($input, 'return_url', '')));

        if ($returnUrl === '') {
            throw new InvalidArgumentException('The OCI cart request is missing HOOK_URL.');
        }

        return new PunchoutCartData(
            protocol: 'oci',
            operation: 'BACKGROUND_POST',
            sessionUuid: $sessionUuid,
            sessionToken: $sessionToken,
            returnUrl: $returnUrl,
            buyerCookie: $this->nullableString(data_get($input, 'BUYER_COOKIE', data_get($input, 'buyer_cookie'))),
            items: $this->normalizeItems(data_get($input, 'items', [])),
            payload: ['source' => 'array'],
        );
    }

    private function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(fn (array $item) => [
                'supplier_part_id' => (string) data_get($item, 'supplier_part_id', data_get($item, 'vendor_mat', '')),
                'description' => (string) data_get($item, 'description', ''),
                'quantity' => (float) data_get($item, 'quantity', 1),
                'price' => (string) data_get($item, 'price', '0.00'),
                'currency' => (string) data_get($item, 'currency', 'USD'),
                'unit_of_measure' => (string) data_get($item, 'unit_of_measure', 'EA'),
            ])
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function requiredString(mixed $value, string $message): string
    {
        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            throw new InvalidArgumentException($message);
        }

        return $stringValue;
    }
}
