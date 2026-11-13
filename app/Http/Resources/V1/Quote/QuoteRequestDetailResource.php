<?php

namespace App\Http\Resources\V1\Quote;

use App\Models\Product;
use App\Models\ProductShipping;
use App\Support\Shipping\UnitConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteRequestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new QuoteRequestResource($this->resource))->resolve($request),
            'business_email' => $this->business_email,
            'department' => $this->department,
            'job_title' => $this->job_title,
            'company_size' => $this->company_size,
            'industry_id' => $this->industry_id,
            'state' => $this->state,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'application' => $this->application,
            'message' => $this->message,
            'requirements' => $this->requirements ?? [],
            'source_page' => $this->source_page,
            'packaging_prefill' => $this->whenLoaded('quotable', fn (): ?array => $this->packagingPrefill()),
            'quotable' => $this->whenLoaded('quotable', fn (): ?array => $this->quotable ? [
                'type' => $this->quotable_type,
                'id' => $this->quotable_id,
                'name' => $this->quotable->name ?? null,
                'route_key' => isset($this->quotable->uuid)
                    ? $this->quotable->slug.'-'.substr($this->quotable->uuid, 0, 8)
                    : null,
            ] : null),
            'tenant' => $this->whenLoaded('tenant', fn (): ?array => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ] : null),
            'organization_ref' => $this->whenLoaded('organization', function (): ?array {
                // `organization` is also a string column holding the buyer's
                // company name, so read the relation explicitly.
                $organization = $this->getRelation('organization');

                return $organization ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ] : null;
            }),
            'quoted_at' => $this->quoted_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'declined_at' => $this->declined_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_reason' => $this->cancelled_reason,
            'responses' => QuoteResponseResource::collection($this->whenLoaded('responses')),
            'activities' => QuoteActivityResource::collection($this->whenLoaded('activities')),
        ];
    }

    /**
     * Packaging defaults copied from the quoted product so the reply composer
     * starts with real shipping data instead of empty fields.
     *
     * @return array<string, mixed>|null
     */
    private function packagingPrefill(): ?array
    {
        $product = $this->quotable;

        if (! $product instanceof Product) {
            return null;
        }

        $shipping = $product->shipping;

        if (! $shipping instanceof ProductShipping) {
            return null;
        }

        return [
            'gross_weight_kg' => $shipping->gross_weight_kg !== null
                ? (float) $shipping->gross_weight_kg
                : null,
            'net_weight_kg' => UnitConverter::toKilograms(
                $shipping->net_weight !== null ? (float) $shipping->net_weight : null,
                $shipping->weight_unit_id,
            ),
            'dimensions' => $this->dimensions($shipping),
            'units_per_case' => (int) $shipping->units_per_case,
            'is_fragile' => (bool) $shipping->is_fragile,
            'is_hazmat' => (bool) $shipping->is_hazmat,
            'is_stackable' => (bool) $shipping->is_stackable,
            'handling_notes' => $shipping->handling_notes,
        ];
    }

    /**
     * Canonical dimensions in centimetres, e.g. "40 × 30 × 20".
     */
    private function dimensions(ProductShipping $shipping): ?string
    {
        $values = array_filter(
            [$shipping->length_cm, $shipping->width_cm, $shipping->height_cm],
            fn ($value): bool => $value !== null,
        );

        if ($values === []) {
            return null;
        }

        return implode(' × ', array_map(
            fn ($value): string => rtrim(rtrim(number_format((float) $value, 1), '0'), '.'),
            $values,
        ));
    }
}
