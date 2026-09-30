<?php

namespace App\Gateways;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves the gateway implementation for a payment method.
 *
 * Drivers come from `config/payment.php`, so a gateway is switched on (or off)
 * without a deploy. A gateway that is switched on but not configured — or whose
 * driver class is missing — is skipped rather than thrown, because a
 * half-finished rollout must not be able to break checkout.
 *
 * @see config/payment.php
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway>|null */
    private ?array $gateways = null;

    public function __construct(
        protected Container $container,
        protected CodGateway $codGateway,
    ) {}

    /**
     * The gateway for a method, ignoring whether it is currently offered.
     *
     * Webhooks use this: a gateway disabled after a payment was taken must
     * still be able to settle it.
     */
    public function implementation(string $method): PaymentGateway
    {
        $gateway = $this->implementations()[$method] ?? null;

        if (! $gateway) {
            throw new InvalidArgumentException("Unsupported payment method: {$method}");
        }

        return $gateway;
    }

    /**
     * The gateway for a method. Throws when the method is unknown or the
     * gateway cannot currently take payments.
     */
    public function gateway(string $method): PaymentGateway
    {
        $gateway = $this->implementation($method);

        if (! $gateway->isEnabled()) {
            throw new InvalidArgumentException("Payment method is not available: {$method}");
        }

        return $gateway;
    }

    public function isAvailable(string $method): bool
    {
        return ($this->implementations()[$method] ?? null)?->isEnabled() === true;
    }

    public function has(string $method): bool
    {
        return isset($this->implementations()[$method]);
    }

    /**
     * Payment methods the storefront may offer, always including COD.
     *
     * @return array<int, array{method: string, label: string, requires_redirect: bool}>
     */
    public function available(?string $currency = null): array
    {
        $methods = [];

        foreach ($this->implementations() as $gateway) {
            if (! $gateway->isEnabled()) {
                continue;
            }

            if (! $gateway->supportsCurrency($currency)) {
                continue;
            }

            $methods[] = [
                'method' => $gateway->method(),
                'label' => $gateway->label(),
                'requires_redirect' => $gateway->method() !== PaymentMethod::COD,
            ];
        }

        return $methods;
    }

    /**
     * Every configured driver, keyed by payment method. COD is added last so a
     * config mistake can never displace it.
     *
     * @return array<string, PaymentGateway>
     */
    private function implementations(): array
    {
        if ($this->gateways !== null) {
            return $this->gateways;
        }

        $gateways = [];

        foreach ((array) config('payment.gateways', []) as $method => $config) {
            $gateway = $this->build((string) $method, (array) $config);

            if ($gateway) {
                $gateways[$gateway->method()] = $gateway;
            }
        }

        $gateways[PaymentMethod::COD] = $this->codGateway;

        return $this->gateways = $gateways;
    }

    /**
     * Drivers are resolved out of the container, which is where a gateway
     * receives its own config (see AppServiceProvider::registerPayments).
     */
    private function build(string $method, array $config): ?PaymentGateway
    {
        $driver = $config['driver'] ?? null;

        if (! is_string($driver) || ! class_exists($driver)) {
            Log::warning('Payment gateway skipped: driver class not found.', [
                'method' => $method,
                'driver' => $driver,
            ]);

            return null;
        }

        $gateway = $this->container->make($driver);

        if (! $gateway instanceof PaymentGateway) {
            Log::warning('Payment gateway skipped: driver does not implement PaymentGateway.', [
                'method' => $method,
                'driver' => $driver,
            ]);

            return null;
        }

        return $gateway;
    }
}
