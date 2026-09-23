<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Knowledge;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class KnowledgeEntryCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => KnowledgeEntryResource::collection($this->collection),
            'pagination' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],
        ];
    }
}
