<?php

namespace App\Http\Resources\V1\Revision;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'revisable_type' => $this->revisable_type,
            'revisable_id' => $this->revisable_id,
            'revision_number' => $this->revision_number,
            'revision_type' => $this->revision_type,
            'reason' => $this->reason,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'created_by_type' => $this->created_by_type,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
