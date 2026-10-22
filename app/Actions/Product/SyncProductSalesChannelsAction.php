<?php

namespace App\Actions\Product;

use App\Enums\SalesChannel as SalesChannelCode;
use App\Models\Product;
use App\Models\SalesChannel;

class SyncProductSalesChannelsAction
{
    /**
     * @param  array<int, string>  $codes
     */
    public function handle(Product $product, array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            $codes = [SalesChannelCode::STOREFRONT];
        }

        $ids = SalesChannel::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->all();

        $product->salesChannels()->sync($ids);
    }
}
