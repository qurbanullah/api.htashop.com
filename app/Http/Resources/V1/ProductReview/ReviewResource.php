<?php

namespace App\Http\Resources\V1\ProductReview;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'is_recommended' => $this->is_recommended,
            'is_verified_purchase' => $this->is_verified_purchase,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
        ];
    }
}
