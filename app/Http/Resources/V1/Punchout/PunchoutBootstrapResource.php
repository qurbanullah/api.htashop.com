<?php

namespace App\Http\Resources\V1\Punchout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PunchoutBootstrapResource extends JsonResource
{
    public function __construct($resource, protected ?string $accessToken = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $settings = is_array($this->tenant?->settings) ? $this->tenant->settings : [];
        $punchout = data_get($settings, 'punchout', []);
        $buyer = $this->buyer;
        $buyerSettings = is_array($buyer?->settings) ? $buyer->settings : [];
        $buyerBranding = $buyer?->branding() ?? [];

        $currency = (string) ($buyer?->default_currency ?: data_get($punchout, 'default_currency', 'USD'));
        $locale = (string) ($buyer?->default_locale ?: data_get($punchout, 'default_locale', 'en'));

        return [
            'session' => [
                'uuid' => $this->uuid,
                'protocol' => $this->protocol,
                'status' => $this->status,
                'expires_at' => optional($this->expires_at)?->toIso8601String(),
                'idle_expires_at' => optional($this->idle_expires_at)?->toIso8601String(),
                'access_token' => $this->accessToken,
            ],
            'tenant' => [
                'uuid' => $this->tenant?->uuid,
                'slug' => $this->tenant?->slug,
                'name' => $this->tenant?->name,
            ],
            'buyer' => $buyer === null ? null : [
                'uuid' => $buyer->uuid,
                'code' => $buyer->code,
                'name' => $buyer->name,
                'customer_part_number_required' => (bool) data_get($buyerSettings, 'customer_part_number_required', true),
                'default_currency' => $buyer->default_currency,
                'default_locale' => $buyer->default_locale,
            ],
            'catalog' => [
                'channel' => 'punchout',
                'currency' => $currency,
                'locale' => $locale,
                'hide_prices' => (bool) data_get($buyerSettings, 'hide_prices', data_get($punchout, 'hide_prices', false)),
                'allow_reviews' => (bool) data_get($buyerSettings, 'allow_reviews', data_get($punchout, 'allow_reviews', false)),
            ],
            'branding' => [
                'logo_url' => data_get($buyerBranding, 'logo_url') ?: data_get($punchout, 'branding.logo_url'),
                'primary_color' => data_get($buyerBranding, 'primary_color') ?: data_get($punchout, 'branding.primary_color'),
            ],
            'limitations' => [
                'max_line_items' => (int) (data_get($buyerSettings, 'max_line_items') ?: data_get($punchout, 'max_line_items', 200)),
                'max_quantity_per_line' => (int) (data_get($buyerSettings, 'max_quantity_per_line') ?: data_get($punchout, 'max_quantity_per_line', 9999)),
            ],
        ];
    }
}
