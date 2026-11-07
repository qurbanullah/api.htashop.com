<?php

namespace App\Services\Shipping;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Support\Shipping\Carriers\CarrierBookingManager;
use App\Support\Shipping\Carriers\CarrierBookingProvider;
use App\Support\Shipping\Carriers\CarrierBookingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a packed shipment into a carrier consignment (TTN + label).
 *
 * Preconditions are checked before any outbound call, and a repeated booking is
 * idempotent: the existing TTN is returned rather than booking twice.
 */
class CarrierBookingService
{
    public function __construct(
        protected CarrierBookingManager $carriers,
        protected ShipmentService $shipments,
    ) {}

    public function book(Shipment $shipment): Shipment
    {
        $shipment->loadMissing(['order.items', 'items.orderItem', 'parcels']);

        if (filled($shipment->tracking_number) && filled($shipment->carrier_booking_id)) {
            return $this->shipments->show($shipment->uuid);
        }

        $provider = $this->providerFor($shipment);

        if (! $shipment->isOpen()) {
            throw ValidationException::withMessages([
                'shipment' => 'Only a shipment that has not been dispatched can be booked.',
            ]);
        }

        if ($shipment->parcels->isEmpty()) {
            throw ValidationException::withMessages([
                'shipment' => 'Record at least one parcel before booking with a carrier.',
            ]);
        }

        $weight = $shipment->parcelWeightKg();

        if ($weight === null || $weight <= 0) {
            throw ValidationException::withMessages([
                'shipment' => 'Record the packed weight before booking with a carrier.',
            ]);
        }

        $order = $shipment->order;
        $shipTo = (array) ($shipment->ship_to ?? []);
        $city = trim((string) data_get($shipTo, 'city'));

        if ($city === '') {
            throw ValidationException::withMessages([
                'shipment' => 'The delivery address has no city, which the carrier needs.',
            ]);
        }

        $result = $provider->book(new CarrierBookingRequest(
            orderNumber: (string) ($order?->order_number ?? $shipment->uuid),
            consigneeName: (string) (
                data_get($shipTo, 'contact_name')
                ?? $order?->customer_name
                ?? 'Customer'
            ),
            consigneePhone: (string) (
                data_get($shipTo, 'phone')
                ?? $order?->customer_phone
                ?? ''
            ),
            consigneeEmail: data_get($shipTo, 'email') ?? $order?->customer_email,
            address: $this->addressLine($shipTo),
            city: $city,
            postalCode: data_get($shipTo, 'postal_code'),
            weightKg: $weight,
            pieces: max(1, $shipment->parcels->count()),
            codAmount: $this->codAmount($order),
            instructions: $shipment->notes ?? $order?->notes,
            productDetail: $shipment->items
                ->map(fn ($item) => trim(($item->orderItem?->name ?? '').' x'.(float) $item->quantity))
                ->filter()
                ->implode(', '),
        ));

        $shipment->update([
            'tracking_number' => $result->trackingNumber,
            'tracking_url' => $result->trackingUrl,
            'carrier_booking_id' => $result->bookingId ?? $result->trackingNumber,
            'label_url' => $result->labelUrl,
            'booked_at' => now(),
        ]);

        return $this->shipments->show($shipment->uuid);
    }

    public function cancelBooking(Shipment $shipment): Shipment
    {
        $shipment->loadMissing('parcels');

        if (blank($shipment->carrier_booking_id) && blank($shipment->tracking_number)) {
            throw ValidationException::withMessages([
                'shipment' => 'This shipment has no carrier booking to cancel.',
            ]);
        }

        if (! $shipment->isOpen()) {
            throw ValidationException::withMessages([
                'shipment' => 'A dispatched shipment cannot be cancelled with the carrier; arrange a return instead.',
            ]);
        }

        $provider = $this->providerFor($shipment);
        $reference = (string) ($shipment->tracking_number ?? $shipment->carrier_booking_id);

        try {
            $provider->cancel($reference);
        } catch (Throwable $exception) {
            // A carrier that cannot be reached must not leave us claiming the
            // booking is gone; the operator retries when the carrier is back.
            Log::warning('Carrier booking cancellation failed', [
                'carrier' => $provider->code(),
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $shipment->update([
            'tracking_number' => null,
            'tracking_url' => null,
            'carrier_booking_id' => null,
            'label_url' => null,
            'booked_at' => null,
        ]);

        return $this->shipments->show($shipment->uuid);
    }

    private function providerFor(Shipment $shipment): CarrierBookingProvider
    {
        $carrier = (string) $shipment->carrier;

        if ($carrier === '' || $carrier === 'manual') {
            throw ValidationException::withMessages([
                'carrier' => 'Choose a carrier before booking this shipment.',
            ]);
        }

        $provider = $this->carriers->forCarrier($carrier);

        if ($provider === null) {
            throw ValidationException::withMessages([
                'carrier' => 'The selected carrier is not configured for booking.',
            ]);
        }

        return $provider;
    }

    /**
     * Cash the courier is expected to collect on our behalf. Paid or online
     * orders collect nothing.
     */
    private function codAmount(?Order $order): float
    {
        if ($order === null) {
            return 0.0;
        }

        $payment = $order->payments()
            ->where('payment_method', 'cod')
            ->latest()
            ->first();

        if ($payment === null || $payment->status === PaymentStatus::PAID) {
            return 0.0;
        }

        return (float) $order->total_amount;
    }

    /**
     * @param  array<string, mixed>  $shipTo
     */
    private function addressLine(array $shipTo): string
    {
        return collect([
            data_get($shipTo, 'address_line_1'),
            data_get($shipTo, 'address_line_2'),
            data_get($shipTo, 'city'),
            data_get($shipTo, 'state'),
            data_get($shipTo, 'postal_code'),
        ])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(', ');
    }
}
