<?php

namespace App\Support\Punchout\Protocols;

use App\Interfaces\Punchout\PunchoutProtocolInterface;
use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutCartData;
use App\Support\Punchout\Data\PunchoutResponseData;
use App\Support\Punchout\Data\PunchoutSetupData;

class OciPunchoutProtocol implements PunchoutProtocolInterface
{
    public function protocol(): string
    {
        return 'oci';
    }

    public function buildSetupContext(Tenant $tenant, array $configuration = []): array
    {
        return [
            'protocol' => $this->protocol(),
            'content_type' => data_get($configuration, 'content_type', 'text/html; charset=UTF-8'),
            'operation' => data_get($configuration, 'setup_operation', 'OCI_LOGIN'),
            'mode' => data_get($configuration, 'mode', 'form'),
            'tenant_uuid' => $tenant->uuid,
            'tenant_slug' => $tenant->slug,
        ];
    }

    public function buildCartContext(Tenant $tenant, array $configuration = []): array
    {
        return [
            'protocol' => $this->protocol(),
            'content_type' => data_get($configuration, 'content_type', 'text/html; charset=UTF-8'),
            'operation' => data_get($configuration, 'cart_operation', 'BACKGROUND_POST'),
            'mode' => data_get($configuration, 'mode', 'form'),
            'tenant_uuid' => $tenant->uuid,
            'tenant_slug' => $tenant->slug,
        ];
    }

    public function renderSetupResponse(Tenant $tenant, PunchoutSetupData $setupData, array $configuration = []): PunchoutResponseData
    {
        $startPageUrl = data_get(
            $configuration,
            'start_page_url',
            rtrim((string) config('app.url', 'http://localhost'), '/') . '/punchout/' . $tenant->slug . '/start?protocol=' . $this->protocol()
        );

        $content = $this->renderAutoSubmitHtml($startPageUrl, [
            'HOOK_URL' => $setupData->returnUrl,
            'BUYER_COOKIE' => (string) $setupData->buyerCookie,
            '~OkCode' => data_get($configuration, 'setup_operation', 'OCI_LOGIN'),
        ]);

        return new PunchoutResponseData($content, data_get($configuration, 'content_type', 'text/html; charset=UTF-8'));
    }

    public function renderCartResponse(Tenant $tenant, PunchoutCartData $cartData, array $configuration = []): PunchoutResponseData
    {
        $fields = [
            'BUYER_COOKIE' => (string) $cartData->buyerCookie,
            '~OkCode' => data_get($configuration, 'cart_operation', 'BACKGROUND_POST'),
        ];

        foreach (array_values($cartData->items) as $index => $item) {
            $line = $index + 1;
            $fields['NEW_ITEM-DESCRIPTION[' . $line . ']'] = $item['description'];
            $fields['NEW_ITEM-QUANTITY[' . $line . ']'] = (string) $item['quantity'];
            $fields['NEW_ITEM-PRICE[' . $line . ']'] = $item['price'];
            $fields['NEW_ITEM-CURRENCY[' . $line . ']'] = $item['currency'];
            $fields['NEW_ITEM-VENDORMAT[' . $line . ']'] = $item['supplier_part_id'];
            $fields['NEW_ITEM-UNIT[' . $line . ']'] = $item['unit_of_measure'];
        }

        $content = $this->renderAutoSubmitHtml($cartData->returnUrl, $fields);

        return new PunchoutResponseData($content, data_get($configuration, 'content_type', 'text/html; charset=UTF-8'));
    }

    private function renderAutoSubmitHtml(string $action, array $fields): string
    {
        $inputs = collect($fields)
            ->map(fn ($value, $name) => '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">')
            ->implode('');

        return '<!DOCTYPE html><html><body onload="document.forms[0].submit()">'
            . '<form method="POST" action="' . e($action) . '">' . $inputs . '</form>'
            . '</body></html>';
    }
}
