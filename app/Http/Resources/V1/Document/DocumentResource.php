<?php

namespace App\Http\Resources\V1\Document;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'download_url' => $this->object_key
                ? config('app.cdn_url', 'https://cdn.htashop.com') . '/' . ltrim($this->object_key, '/')
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
