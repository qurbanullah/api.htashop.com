<?php

namespace App\Support\Shipping\Carriers;

use Illuminate\Contracts\Container\Container;

/**
 * Resolves the carrier providers enabled in `config/shipping.php`.
 *
 * A provider is only returned when it is both enabled and configured, so a
 * half-filled environment can never break a quote.
 */
class CarrierRateManager
{
    public function __construct(
        protected Container $container,
    ) {}

    /**
     * @return CarrierRateProvider[]
     */
    public function active(): array
    {
        $providers = [];

        foreach ((array) config('shipping.carriers', []) as $config) {
            if (! is_array($config) || ! ($config['enabled'] ?? false)) {
                continue;
            }

            $driver = $config['driver'] ?? null;

            if (! is_string($driver) || ! class_exists($driver)) {
                continue;
            }

            $provider = $this->container->make($driver, ['config' => $config]);

            if ($provider instanceof CarrierRateProvider && $provider->isConfigured()) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
