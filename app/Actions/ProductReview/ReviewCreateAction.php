<?php

namespace App\Actions\ProductReview;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Str;

class ReviewCreateAction
{
    public function handle(string $type, int $id, ?int $tenantId, array $data, ?User $user): Review
    {
        return Review::create([
            'uuid' => (string) Str::uuid(),
            'reviewable_type' => $type,
            'reviewable_id' => $id,
            'user_id' => $user?->id,
            'tenant_id' => $tenantId,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'is_recommended' => $data['is_recommended'] ?? false,
            'is_verified_purchase' => false,
            'status' => 'approved',
        ]);
    }
}
