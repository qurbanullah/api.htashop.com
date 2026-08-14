<?php

namespace App\Services\Punchout;

use App\Interfaces\Punchout\PunchoutProtocolInterface;
use App\Models\Tenant;
use App\Support\Punchout\Protocols\CxmlPunchoutProtocol;
use App\Support\Punchout\Protocols\OciPunchoutProtocol;
use InvalidArgumentException;

class PunchoutProtocolService
{
    /**
     * @var array<string, PunchoutProtocolInterface>
     */
    private array $protocols;

    public function __construct(
        CxmlPunchoutProtocol $cxmlPunchoutProtocol,
        OciPunchoutProtocol $ociPunchoutProtocol,
    ) {
        $this->protocols = [
            $cxmlPunchoutProtocol->protocol() => $cxmlPunchoutProtocol,
            $ociPunchoutProtocol->protocol() => $ociPunchoutProtocol,
        ];
    }

    public function resolve(?Tenant $tenant = null, ?string $protocol = null): PunchoutProtocolInterface
    {
        $configuration = $this->configuration($tenant);
        $requestedProtocol = $protocol ?: data_get($configuration, 'default_protocol');

        if (!in_array($requestedProtocol, data_get($configuration, 'supported_protocols', []), true)) {
            throw new InvalidArgumentException('Unsupported punchout protocol for the current tenant configuration.');
        }

        $handler = $this->protocols[$requestedProtocol] ?? null;

        if (!$handler) {
            throw new InvalidArgumentException('Punchout protocol handler is not registered.');
        }

        return $handler;
    }

    public function configuration(?Tenant $tenant = null): array
    {
        $defaultProtocol = config('punchout.default_protocol', 'cxml');
        $supportedProtocols = array_values(array_filter(
            config('punchout.supported_protocols', ['cxml', 'oci']),
            fn ($protocol) => isset($this->protocols[$protocol])
        ));

        $tenantPunchout = is_array($tenant?->settings) ? data_get($tenant->settings, 'punchout', []) : [];
        $tenantSupportedProtocols = collect(data_get($tenantPunchout, 'supported_protocols', $supportedProtocols))
            ->filter(fn ($protocol) => isset($this->protocols[$protocol]))
            ->unique()
            ->values()
            ->all();

        $resolvedSupportedProtocols = $tenantSupportedProtocols !== [] ? $tenantSupportedProtocols : $supportedProtocols;
        $resolvedDefaultProtocol = data_get($tenantPunchout, 'default_protocol', $defaultProtocol);

        if (!in_array($resolvedDefaultProtocol, $resolvedSupportedProtocols, true)) {
            $resolvedDefaultProtocol = $resolvedSupportedProtocols[0] ?? $defaultProtocol;
        }

        return [
            'default_protocol' => $resolvedDefaultProtocol,
            'supported_protocols' => $resolvedSupportedProtocols,
        ];
    }

    public function buildSetupContext(Tenant $tenant, ?string $protocol = null): array
    {
        $handler = $this->resolve($tenant, $protocol);

        return $handler->buildSetupContext($tenant, $this->protocolConfiguration($tenant, $handler->protocol()));
    }

    public function buildCartContext(Tenant $tenant, ?string $protocol = null): array
    {
        $handler = $this->resolve($tenant, $protocol);

        return $handler->buildCartContext($tenant, $this->protocolConfiguration($tenant, $handler->protocol()));
    }

    public function protocolConfiguration(?Tenant $tenant, string $protocol): array
    {
        $baseConfiguration = config('punchout.protocols.' . $protocol, []);
        $tenantConfiguration = is_array($tenant?->settings)
            ? data_get($tenant->settings, 'punchout.protocols.' . $protocol, [])
            : [];

        return array_merge($baseConfiguration, is_array($tenantConfiguration) ? $tenantConfiguration : []);
    }
}
