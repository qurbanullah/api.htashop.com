<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\ShippingRate;

class StoreShippingRateRequest extends ShippingConfigRequest
{
    use ValidatesShippingRate;

    protected function modelClass(): string
    {
        return ShippingRate::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rateRules(partial: false);
    }
}
