<?php

namespace App\Http\Requests\V1\Shipment;

class StoreShipmentRequest extends ShipmentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->shipmentRules(),
            $this->autoPackRules(),
            $this->itemRules(required: true),
            $this->parcelRules(),
        );
    }
}
