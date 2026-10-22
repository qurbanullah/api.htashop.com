<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\BuildPunchoutSessionHandoffUrlAction;
use App\Actions\Punchout\BuildPunchoutSessionStartUrlAction;
use App\Actions\Punchout\CompletePunchoutSessionAction;
use App\Actions\Punchout\CreatePunchoutSessionAction;
use App\Actions\Punchout\IssueHandoffCodeAction;
use App\Actions\Punchout\NormalizePunchoutCartDataAction;
use App\Actions\Punchout\NormalizePunchoutSetupDataAction;
use App\Actions\Punchout\ResolvePunchoutBuyerAction;
use App\Actions\Punchout\ResolvePunchoutCartSessionAction;
use App\Actions\Punchout\ResolvePunchoutSessionAction;
use App\Actions\Punchout\ResolvePunchoutTenantAction;
use App\Actions\Punchout\ValidatePunchoutReturnUrlAction;
use App\Actions\Punchout\ValidatePunchoutSetupCredentialsAction;
use App\Helpers\CacheHelper;
use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutCartData;
use App\Support\Punchout\Data\PunchoutResponseData;
use App\Support\Punchout\Data\PunchoutSetupData;
use App\Support\Punchout\Data\PunchoutStartResultData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        protected IssueHandoffCodeAction $issueHandoffCodeAction,
        protected ValidatePunchoutReturnUrlAction $validatePunchoutReturnUrlAction,
        protected ResolvePunchoutBuyerAction $resolvePunchoutBuyerAction,
    ) {}

    public function setup(string $tenantIdentifier, array $input, ?string $rawBody = null, ?string $protocol = null): PunchoutResponseData
    {
        $tenant = $this->resolveTenant($tenantIdentifier);
        $handler = $this->punchoutProtocolService->resolve($tenant, $protocol ?: data_get($input, 'protocol'));
        $setupData = $this->normalizePunchoutSetupDataAction->handle($handler->protocol(), $input, $rawBody);
        $configuration = $this->punchoutProtocolService->protocolConfiguration($tenant, $handler->protocol());
        $credentials = $this->setupCredentials($setupData);
        $buyer = $this->resolvePunchoutBuyerAction->handle($tenant, $setupData->protocol, $credentials);

        // A tenant that has onboarded buyers must not fall back to the shared
        // tenant-level secret: an unknown sender is a configuration error, not
        // a new buyer.
        if ($buyer === null && $this->resolvePunchoutBuyerAction->hasActiveBuyers($tenant, $setupData->protocol)) {
            throw ValidationException::withMessages([
                'credentials' => ['No active punchout buyer matches these credentials.'],
            ]);
        }

        if ($buyer === null) {
            $this->validatePunchoutSetupCredentialsAction->handle($tenant, $setupData, $configuration);
        }

        $session = $this->createPunchoutSessionAction->handle($tenant, $setupData, $configuration, $buyer);

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

        // The setup request owns the return URL. Reject a cart that tries to
        // post the basket somewhere else, then render to the stored URL.
        $this->validatePunchoutReturnUrlAction->handle($session, $cartData->returnUrl);

        $cartData = new PunchoutCartData(
            protocol: $cartData->protocol,
            operation: $cartData->operation,
            sessionUuid: $cartData->sessionUuid,
            sessionToken: $cartData->sessionToken,
            returnUrl: $session->return_url,
            buyerCookie: $cartData->buyerCookie,
            items: $cartData->items,
            payload: $cartData->payload,
        );

        return DB::transaction(function () use ($tenant, $cartData, $configuration, $session, $handler): PunchoutResponseData {
            $this->completePunchoutSessionAction->handle($session, $cartData);

            return $handler->renderCartResponse($tenant, $cartData, $configuration);
        });
    }

    public function start(string $tenantIdentifier, string $sessionUuid, string $token): PunchoutStartResultData
    {
        $tenant = $this->resolveTenant($tenantIdentifier);
        $session = $this->resolvePunchoutSessionAction->handle($tenant, $sessionUuid, $token);
        $handoffCode = $this->issueHandoffCodeAction->handle($session);

        return new PunchoutStartResultData(
            session: $session->load('tenant'),
            redirectUrl: $this->buildPunchoutSessionHandoffUrlAction->handle(
                $tenant,
                $session,
                $this->punchoutProtocolService->protocolConfiguration($tenant, $session->protocol),
                $handoffCode,
            ),
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function setupCredentials(PunchoutSetupData $setupData): array
    {
        return match ($setupData->protocol) {
            'cxml' => [
                'sender_identity' => data_get($setupData->payload, 'sender.identity'),
                'shared_secret' => data_get($setupData->payload, 'sender.shared_secret'),
                'from_identity' => data_get($setupData->payload, 'from.credential'),
                'to_identity' => data_get($setupData->payload, 'to.credential'),
            ],
            'oci' => [
                'username' => data_get($setupData->payload, 'username'),
                'password' => data_get($setupData->payload, 'password'),
            ],
            default => [],
        };
    }

    private function resolveTenant(string $identifier): Tenant
    {
        return CacheHelper::remember(
            ['punchout', 'tenant'],
            "punchout:tenant:{$identifier}",
            600,
            fn () => $this->resolvePunchoutTenantAction->handle($identifier)
        );
    }
}
