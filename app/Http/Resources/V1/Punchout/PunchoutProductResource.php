<?php

namespace App\Http\Resources\V1\Punchout;

use App\Http\Resources\V1\Catalog\CatalogProductResource;
use Illuminate\Http\Request;

class PunchoutProductResource extends CatalogProductResource
{
    /**
     * @param  mixed  $resource
     * @param  array<string, mixed>  $pricing  Resolved buyer pricing; empty when none matched.
     */
    public function __construct($resource, protected array $pricing = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->pricing === []) {
            return $data;
        }

        $data['price'] = $this->pricing['base_price'] ?? $this->pricing['unit_price'];
        $data['sale_price'] = $this->pricing['sale_price'];
        $data['unit_price'] = $this->pricing['unit_price'];
        $data['currency'] = $this->pricing['currency'];
        $data['price_source'] = $this->pricing['source'];
        $data['customer_part_number'] = $this->pricing['customer_part_number'];
        $data['unit_of_measure'] = $this->pricing['unit_of_measure'];
        $data['min_quantity'] = $this->pricing['min_quantity'];

        if (($this->pricing['lead_time_days'] ?? null) !== null) {
            $data['lead_time_days'] = $this->pricing['lead_time_days'];
        }

        return $data;
    }
}
