<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Coupon;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recorded use of a code, for the admin's "who used this" table.
 *
 * Guests have no user, so `session_id` is what identifies them.
 */
class CouponRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'code' => $this->code,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'order_uuid' => $this->order?->uuid,
            'order_number' => $this->order?->order_number,
            'user_name' => $this->user?->name,
            'user_email' => $this->user?->email,
            'guest' => $this->user_id === null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
