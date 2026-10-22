<?php

namespace App\Actions\Punchout;

use App\Support\Punchout\Data\PunchoutOrderRequestData;
use InvalidArgumentException;

class NormalizePunchoutOrderRequestAction
{
    /**
     * Parse an inbound cXML OrderRequest. OCI has no equivalent inbound order
     * document, so only cXML is accepted here.
     */
    public function handle(string $protocol, ?string $rawBody): PunchoutOrderRequestData
    {
        if ($protocol !== 'cxml') {
            throw new InvalidArgumentException('Only cXML inbound orders are supported.');
        }

        if (! $rawBody) {
            throw new InvalidArgumentException('A cXML order request body is required.');
        }

        $xml = @simplexml_load_string($rawBody);

        if (! $xml) {
            throw new InvalidArgumentException('The cXML order request body is invalid.');
        }

        $orderRequest = $xml->Request->OrderRequest ?? null;
        $header = $orderRequest?->OrderRequestHeader ?? null;
        $payloadId = trim((string) ($xml['payloadID'] ?? ''));

        if ($payloadId === '') {
            throw new InvalidArgumentException('The cXML order request is missing payloadID.');
        }

        $lines = [];

        foreach ($orderRequest?->ItemOut ?? [] as $itemOut) {
            $itemDetail = $itemOut->ItemDetail ?? null;
            $money = $itemDetail?->UnitPrice?->Money ?? null;
            $itemId = $itemOut->ItemID ?? null;

            $lines[] = [
                'line_number' => $this->nullableString($itemOut['lineNumber'] ?? null),
                'supplier_part_id' => $this->nullableString($itemId?->SupplierPartID ?? null),
                'customer_part_number' => $this->nullableString($itemId?->BuyerPartID ?? null),
                'description' => $this->nullableString($itemDetail?->Description ?? null),
                'quantity' => max(1.0, (float) ($itemOut['quantity'] ?? 1)),
                'unit_price' => $money !== null ? (float) $money : null,
                'currency' => $this->nullableString($money['currency'] ?? null),
                'unit_of_measure' => $this->nullableString($itemDetail?->UnitOfMeasure ?? null),
            ];
        }

        if ($lines === []) {
            throw new InvalidArgumentException('The cXML order request has no line items.');
        }

        $totalMoney = $header?->Total?->Money ?? null;
        $currency = $this->nullableString($totalMoney['currency'] ?? null)
            ?? collect($lines)->pluck('currency')->filter()->first();
        $shipToAddress = $header?->ShipTo?->Address ?? null;
        $country = $shipToAddress?->Country ?? null;
        $countryCode = $country !== null
            ? $this->nullableString($country['isoCountryCode'] ?? (string) $country)
            : null;
        $orderId = $this->nullableString($header['orderID'] ?? null);
        $orderDate = $this->nullableString($header['orderDate'] ?? null);

        return new PunchoutOrderRequestData(
            protocol: 'cxml',
            payloadId: $payloadId,
            orderId: $orderId,
            orderDate: $orderDate,
            currency: $currency !== null ? strtoupper($currency) : null,
            declaredTotal: $totalMoney !== null ? (float) $totalMoney : null,
            lines: $lines,
            shipTo: [
                'name' => $this->nullableString($shipToAddress?->Name ?? null),
                'street' => $this->nullableString($shipToAddress?->Street ?? null),
                'city' => $this->nullableString($shipToAddress?->City ?? null),
                'state' => $this->nullableString($shipToAddress?->State ?? null),
                'postal_code' => $this->nullableString($shipToAddress?->PostalCode ?? null),
                'country' => $countryCode,
                'email' => $this->nullableString($header?->ShipTo?->Email ?? null),
            ],
            credentials: [
                'sender_identity' => $this->nullableString($xml->Header?->Sender?->Credential?->Identity ?? null),
                'shared_secret' => $this->nullableString($xml->Header?->Sender?->Credential?->SharedSecret ?? null),
                'from_identity' => $this->nullableString($xml->Header?->From?->Credential?->Identity ?? null),
                'to_identity' => $this->nullableString($xml->Header?->To?->Credential?->Identity ?? null),
            ],
            payload: [
                'order_id' => $orderId,
                'order_date' => $orderDate,
                'line_count' => count($lines),
            ],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }
}
