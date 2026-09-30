<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Coupon;

use App\Enums\CouponType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * `status` is what the admin list groups by — it answers "will this code
     * apply right now, and if not, why not", which the raw flags do not.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            // Scope: organization beats tenant beats global. `scope` is the
            // single word the UI groups by; the ids drive the pickers.
            'scope' => $this->scopeLabel(),
            'tenant_id' => $this->tenant_id !== null ? (int) $this->tenant_id : null,
            'organization_id' => $this->organization_id !== null ? (int) $this->organization_id : null,
            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant?->name),
            'organization_name' => $this->whenLoaded('organization', fn () => $this->organization?->name),

            'code' => $this->code,
            'label' => $this->label,
            'type' => $this->type,
            'type_label' => CouponType::label((string) $this->type),
            'value' => (float) $this->value,
            'description' => $this->describe(),
            'min_order_amount' => $this->min_order_amount !== null ? (float) $this->min_order_amount : null,
            'max_discount_amount' => $this->max_discount_amount !== null ? (float) $this->max_discount_amount : null,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'per_user_limit' => $this->per_user_limit,
            'used_count' => (int) $this->used_count,
            // null means "unlimited", not "none left".
            'remaining' => $this->usage_limit === null
                ? null
                : max(0, (int) $this->usage_limit - (int) $this->used_count),
            'is_active' => (bool) $this->is_active,
            'status' => $this->status(),
            'redemptions_count' => $this->whenCounted('redemptions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
