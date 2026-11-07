<?php

namespace App\Support\Shipping\Carriers;

use Illuminate\Contracts\Container\Container;

/**
 * Resolves the booking providers enabled in `config/shipping.php`.
 *
 * A provider is only returned when it is both enabled and configured, so a
 * half-filled environment can never book a shipment it cannot cancel.
 */
class CarrierBookingManager
{
    public function __construct(
        protected Container $container,
    ) {}

    /**
     * @return CarrierBookingProvider[]
     */
    public function active(): array
    {
        $providers = [];

        foreach ((array) config('shipping.carriers', []) as $config) {
            if (! is_array($config) || ! ($config['enabled'] ?? false)) {
                continue;
            }

            $driver = $config['booking_driver'] ?? null;

            if (! is_string($driver) || ! class_exists($driver)) {
                continue;
            }

            $provider = $this->container->make($driver, ['config' => $config]);

            if ($provider instanceof CarrierBookingProvider && $provider->isConfigured()) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    public function forCarrier(?string $carrier): ?CarrierBookingProvider
    {
        if ($carrier === null || $carrier === '') {
            return null;
        }

        foreach ($this->active() as $provider) {
            if ($provider->code() === $carrier) {
                return $provider;
            }
        }

        return null;
    }
}
