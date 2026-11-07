<?php

namespace App\Http\Requests\V1\Shipment;

class UpdateShipmentRequest extends ShipmentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->shipmentRules(),
            $this->itemRules(required: false),
            $this->parcelRules(),
        );
    }
}
