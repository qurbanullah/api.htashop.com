<?php

namespace App\Support\Punchout\Protocols;

use App\Interfaces\Punchout\PunchoutProtocolInterface;
use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutCartData;
use App\Support\Punchout\Data\PunchoutResponseData;
use App\Support\Punchout\Data\PunchoutSetupData;
use Illuminate\Support\Str;

class CxmlPunchoutProtocol implements PunchoutProtocolInterface
{
    public function protocol(): string
    {
        return 'cxml';
    }

    public function buildSetupContext(Tenant $tenant, array $configuration = []): array
    {
        return [
            'protocol' => $this->protocol(),
            'content_type' => data_get($configuration, 'content_type', 'text/xml'),
            'operation' => data_get($configuration, 'setup_operation', 'PunchOutSetupRequest'),
            'mode' => data_get($configuration, 'mode', 'request'),
            'tenant_uuid' => $tenant->uuid,
            'tenant_slug' => $tenant->slug,
        ];
    }

    public function buildCartContext(Tenant $tenant, array $configuration = []): array
    {
        return [
            'protocol' => $this->protocol(),
            'content_type' => data_get($configuration, 'content_type', 'text/xml'),
            'operation' => data_get($configuration, 'cart_operation', 'PunchOutOrderMessage'),
            'mode' => data_get($configuration, 'mode', 'request'),
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

        $payloadId = $tenant->uuid . '-setup-' . now()->format('YmdHis') . '-' . Str::random(8);

        $content = implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<cXML payloadID="' . e($payloadId) . '" timestamp="' . now()->toIso8601String() . '">',
            '  <Response>',
            '    <Status code="200" text="OK">Success</Status>',
            '    <PunchOutSetupResponse>',
            '      <StartPage>',
            '        <URL>' . e($startPageUrl) . '</URL>',
            '      </StartPage>',
            '    </PunchOutSetupResponse>',
            '  </Response>',
            '</cXML>',
        ]);

        return new PunchoutResponseData($content, data_get($configuration, 'content_type', 'text/xml'));
    }

    public function renderCartResponse(Tenant $tenant, PunchoutCartData $cartData, array $configuration = []): PunchoutResponseData
    {
        $cartPayloadId = $tenant->uuid . '-cart-' . now()->format('YmdHis') . '-' . Str::random(8);

        $messageXml = implode('', [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<cXML payloadID="' . e($cartPayloadId) . '" timestamp="' . now()->toIso8601String() . '">',
            '<Message>',
            '<PunchOutOrderMessage>',
            '<BuyerCookie>' . e((string) $cartData->buyerCookie) . '</BuyerCookie>',
            '<PunchOutOrderMessageHeader operationAllowed="create"/>',
            collect($cartData->items)->map(function (array $item, int $index): string {
                return '<ItemIn quantity="' . e((string) $item['quantity']) . '" lineNumber="' . ($index + 1) . '">'
                    . '<ItemID><SupplierPartID>' . e($item['supplier_part_id']) . '</SupplierPartID></ItemID>'
                    . '<ItemDetail>'
                    . '<UnitPrice><Money currency="' . e($item['currency']) . '">' . e($item['price']) . '</Money></UnitPrice>'
                    . '<Description xml:lang="en">' . e($item['description']) . '</Description>'
                    . '<UnitOfMeasure>' . e($item['unit_of_measure']) . '</UnitOfMeasure>'
                    . '</ItemDetail>'
                    . '</ItemIn>';
            })->implode(''),
            '</PunchOutOrderMessage>',
            '</Message>',
            '</cXML>',
        ]);

        $content = $this->renderAutoSubmitHtml($cartData->returnUrl, [
            'cXML-urlencoded' => $messageXml,
        ]);

        return new PunchoutResponseData($content, 'text/html; charset=UTF-8');
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
