<?php

namespace App\Interfaces\Punchout;

use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutCartData;
use App\Support\Punchout\Data\PunchoutResponseData;
use App\Support\Punchout\Data\PunchoutSetupData;

interface PunchoutProtocolInterface
{
    public function protocol(): string;

    public function buildSetupContext(Tenant $tenant, array $configuration = []): array;

    public function buildCartContext(Tenant $tenant, array $configuration = []): array;

    public function renderSetupResponse(Tenant $tenant, PunchoutSetupData $setupData, array $configuration = []): PunchoutResponseData;

    public function renderCartResponse(Tenant $tenant, PunchoutCartData $cartData, array $configuration = []): PunchoutResponseData;
}
