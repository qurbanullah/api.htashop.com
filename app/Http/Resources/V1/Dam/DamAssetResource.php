<?php

namespace App\Http\Resources\V1\Dam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'damable_type' => $this->damable_type,
            'damable_id' => $this->damable_id,
            'collection_name' => $this->collection_name,
            'sort_order' => $this->sort_order,
            'file_name' => $this->file_name,
            'disk' => $this->disk,
            'bucket' => $this->bucket,
            'object_key' => $this->object_key,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'origin_url' => $this->origin_url,
            'is_current' => $this->is_current,
            'version' => $this->version,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
