<?php

namespace App\Http\Resources\V1\Punchout;

use App\Http\Resources\V1\Catalog\CatalogProductDetailResource;
use Illuminate\Http\Request;

class PunchoutProductDetailResource extends CatalogProductDetailResource
{
    /**
     * @param  mixed  $resource
     * @param  array<string, mixed>  $pricing
     * @param  array<int, array<string, mixed>>  $variantPricing  Keyed by variant id.
     */
    public function __construct(
        $resource,
        protected array $pricing = [],
        protected array $variantPricing = [],
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->pricing !== []) {
            $data['price'] = $this->pricing['base_price'] ?? $this->pricing['unit_price'];
            $data['sale_price'] = $this->pricing['sale_price'];
            $data['unit_price'] = $this->pricing['unit_price'];
            $data['currency'] = $this->pricing['currency'];
            $data['price_source'] = $this->pricing['source'];
            $data['customer_part_number'] = $this->pricing['customer_part_number'];
            $data['unit_of_measure'] = $this->pricing['unit_of_measure'];
            $data['min_quantity'] = $this->pricing['min_quantity'];
        }

        if (isset($data['variants']) && is_array($data['variants'])) {
            $data['variants'] = array_map(function (array $variant): array {
                $pricing = $this->variantPricing[(int) ($variant['id'] ?? 0)] ?? [];

                if ($pricing === []) {
                    return $variant;
                }

                $variant['price'] = $pricing['base_price'] ?? $pricing['unit_price'];
                $variant['sale_price'] = $pricing['sale_price'];
                $variant['unit_price'] = $pricing['unit_price'];
                $variant['currency'] = $pricing['currency'];
                $variant['customer_part_number'] = $pricing['customer_part_number'];
                $variant['unit_of_measure'] = $pricing['unit_of_measure'];

                return $variant;
            }, $data['variants']);
        }

        return $data;
    }
}
