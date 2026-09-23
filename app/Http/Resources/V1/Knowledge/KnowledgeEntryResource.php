<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Knowledge;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'locale' => $this->locale,
            'title' => $this->title,
            'slug' => $this->slug,
            'question' => $this->question,
            'body' => $this->body,
            'source_type' => $this->source_type?->value,
            'source_id' => $this->source_id,
            'source_url' => $this->source_url,
            'tags' => $this->tags ?? [],
            'status' => $this->status?->value,
            'restricted' => (bool) $this->restricted,
            'priority' => (int) $this->priority,
            'created_by' => $this->created_by,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
