<?php

namespace App\Models\Builders;

use App\Enums\SalesChannel;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Product>
 */
class ProductBuilder extends Builder
{
    /**
     * Scope to the channels a product is published in.
     *
     * A product with no channel memberships is treated as storefront-only so
     * rows created before the channel rollout do not disappear from the B2C
     * catalog. Punchout always requires an explicit membership.
     */
    /**
     * @param  string|array<int, string>|null  $channel
     */
    public function forChannel(string|array|null $channel): static
    {
        $channels = is_array($channel) ? $channel : [$channel];
        $channels = array_values(array_filter($channels));

        if ($channels === []) {
            return $this;
        }

        return $this->where(function (Builder $inner) use ($channels): void {
            $inner->whereHas(
                'salesChannels',
                fn (Builder $relation) => $relation->whereIn('code', $channels),
            );

            if (in_array(SalesChannel::STOREFRONT, $channels, true)) {
                $inner->orWhereDoesntHave('salesChannels');
            }
        });
    }

    public function forTenant(?int $tenantId): static
    {
        if ($tenantId !== null) {
            $this->where('tenant_id', $tenantId);
        }

        return $this;
    }
}
