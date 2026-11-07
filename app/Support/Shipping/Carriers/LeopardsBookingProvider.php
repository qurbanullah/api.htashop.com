<?php

namespace App\Support\Shipping\Carriers;

use App\Support\Shipping\Carriers\Leopards\LeopardsClient;
use App\Support\Shipping\Carriers\Leopards\LeopardsException;

/**
 * Books a packed shipment with Leopards and returns the consignment's TTN and
 * label. One booking covers the whole shipment; `pieces` is the number of boxes.
 */
class LeopardsBookingProvider implements CarrierBookingProvider
{
    protected ?LeopardsClient $client = null;

    public function __construct(
        protected array $config = [],
    ) {}

    public function code(): string
    {
        return 'leopards';
    }

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    public function book(CarrierBookingRequest $request): CarrierBookingResult
    {
        $body = $this->client()->book([
            'booked_packet_weight' => round($request->weightKg, 3),
            'booked_packet_no_piece' => max(1, $request->pieces),
            'booked_packet_collect_amount' => round($request->codAmount, 2),
            'booked_packet_order_id' => $request->orderNumber,
            'origin_city' => (string) ($this->config['origin_city'] ?? 'Lahore'),
            'destination_city' => $request->city,
            'shipment_type' => (string) ($this->config['shipment_type'] ?? 'overnight'),
            'shipment_booking_date' => now()->format('Y-m-d'),
            'consignment_name_eng' => $request->consigneeName,
            'consignment_phone' => $request->consigneePhone,
            'consignment_email' => $request->consigneeEmail ?? '',
            'consignment_address' => $request->address,
            'consignment_postal_code' => $request->postalCode ?? '',
            'special_instructions' => $request->instructions ?? '',
            'product_detail' => $request->productDetail ?? '',
        ]);

        $tracking = $this->stringOrNull(data_get($body, 'packet_list.0.track_number'))
            ?? $this->stringOrNull(data_get($body, 'packet_list.0.booked_packet_no'))
            ?? $this->stringOrNull(data_get($body, 'track_number'))
            ?? $this->stringOrNull(data_get($body, 'booked_packet_no'));

        if ($tracking === null) {
            throw new LeopardsException('Leopards did not return a tracking number.');
        }

        return new CarrierBookingResult(
            bookingId: $this->stringOrNull(data_get($body, 'packet_list.0.booked_packet_id'))
                ?? $this->stringOrNull(data_get($body, 'booked_packet_id')),
            trackingNumber: $tracking,
            labelUrl: $this->stringOrNull(data_get($body, 'slip_link'))
                ?? $this->stringOrNull(data_get($body, 'packet_list.0.slip_link'))
                ?? $this->stringOrNull(data_get($body, 'booked_packet_slip')),
            trackingUrl: $this->stringOrNull(data_get($body, 'track_url'))
                ?? $this->stringOrNull(data_get($body, 'packet_list.0.track_url')),
            raw: $body,
        );
    }

    public function cancel(string $reference): void
    {
        // Leopards voids a booking by its packet number (the TTN).
        $this->client()->cancel([
            'booked_packet_no' => $reference,
            'track_number' => $reference,
        ]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function client(): LeopardsClient
    {
        return $this->client ??= new LeopardsClient($this->config);
    }
}
