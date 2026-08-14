<?php

namespace App\Http\Resources\V1\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'symbol' => $this->symbol,
            'measurement' => $this->whenLoaded('measurement', fn () => [
                'id' => $this->measurement->id,
                'name' => $this->measurement->name,
                'code' => $this->measurement->code,
            ]),
        ];
    }
}
