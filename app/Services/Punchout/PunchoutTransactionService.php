<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\BuildPunchoutSessionHandoffUrlAction;
use App\Actions\Punchout\BuildPunchoutSessionStartUrlAction;
use App\Actions\Punchout\CompletePunchoutSessionAction;
use App\Actions\Punchout\CreatePunchoutSessionAction;
use App\Actions\Punchout\NormalizePunchoutCartDataAction;
use App\Actions\Punchout\NormalizePunchoutSetupDataAction;
use App\Actions\Punchout\ResolvePunchoutCartSessionAction;
use App\Actions\Punchout\ResolvePunchoutTenantAction;
use App\Actions\Punchout\ResolvePunchoutSessionAction;
use App\Actions\Punchout\ValidatePunchoutSetupCredentialsAction;
use App\Models\PunchoutSession;
use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutStartResultData;
use App\Support\Punchout\Data\PunchoutResponseData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PunchoutTransactionService
{
    public function __construct(
        protected ResolvePunchoutTenantAction $resolvePunchoutTenantAction,
        protected CreatePunchoutSessionAction $createPunchoutSessionAction,
        protected ResolvePunchoutCartSessionAction $resolvePunchoutCartSessionAction,
        protected CompletePunchoutSessionAction $completePunchoutSessionAction,
        protected ResolvePunchoutSessionAction $resolvePunchoutSessionAction,
        protected BuildPunchoutSessionStartUrlAction $buildPunchoutSessionStartUrlAction,
        protected BuildPunchoutSessionHandoffUrlAction $buildPunchoutSessionHandoffUrlAction,
        protected ValidatePunchoutSetupCredentialsAction $validatePunchoutSetupCredentialsAction,
        protected NormalizePunchoutSetupDataAction $normalizePunchoutSetupDataAction,
        protected NormalizePunchoutCartDataAction $normalizePunchoutCartDataAction,
        protected PunchoutProtocolService $punchoutProtocolService,
    ) {
    }

    public function setup(string $tenantIdentifier, array $input, ?string $rawBody = null, ?string $protocol = null): PunchoutResponseData
    {
        $tenant = $this->resolveTenant($tenantIdentifier);
        $handler = $this->punchoutProtocolService->resolve($tenant, $protocol ?: data_get($input, 'protocol'));
        $setupData = $this->normalizePunchoutSetupDataAction->handle($handler->protocol(), $input, $rawBody);
        $configuration = $this->punchoutProtocolService->protocolConfiguration($tenant, $handler->protocol());

        $this->validatePunchoutSetupCredentialsAction->handle($tenant, $setupData, $configuration);

        $session = $this->createPunchoutSessionAction->handle($tenant, $setupData, $configuration);

        return $handler->renderSetupResponse(
            $tenant,
            $setupData,
            array_merge($configuration, [
                'start_page_url' => $this->buildPunchoutSessionStartUrlAction->handle($tenant, $session),
            ])
        );
    }

    public function cart(string $tenantIdentifier, array $input, ?string $rawBody = null, ?string $protocol = null): PunchoutResponseData
    {
        $tenant = $this->resolveTenant($tenantIdentifier);
        $handler = $this->punchoutProtocolService->resolve($tenant, $protocol ?: data_get($input, 'protocol'));
        $cartData = $this->normalizePunchoutCartDataAction->handle($handler->protocol(), $input, $rawBody);
        $session = $this->resolvePunchoutCartSessionAction->handle($tenant, $cartData);
        $configuration = $this->punchoutProtocolService->protocolConfiguration($tenant, $handler->protocol());

        return DB::transaction(function () use ($tenant, $cartData, $configuration, $session, $handler): PunchoutResponseData {
            $this->completePunchoutSessionAction->handle($session, $cartData);

            return $handler->renderCartResponse($tenant, $cartData, $configuration);
        });
    }

    public function start(string $tenantIdentifier, string $sessionUuid, string $token): PunchoutStartResultData
    {
        $tenant = $this->resolveTenant($tenantIdentifier);
        $session = $this->resolvePunchoutSessionAction->handle($tenant, $sessionUuid, $token);

        return new PunchoutStartResultData(
            session: $session->load('tenant'),
            redirectUrl: $this->buildPunchoutSessionHandoffUrlAction->handle(
                $tenant,
                $session,
                $this->punchoutProtocolService->protocolConfiguration($tenant, $session->protocol)
            ),
        );
    }

    private function resolveTenant(string $identifier): Tenant
    {
        return \App\Helpers\CacheHelper::remember(
            ['punchout', 'tenant'],
            "punchout:tenant:{$identifier}",
            600,
            fn () => $this->resolvePunchoutTenantAction->handle($identifier)
        );
    }
}
