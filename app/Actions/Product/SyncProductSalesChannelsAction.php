<?php

namespace App\Actions\Product;

use App\Enums\SalesChannel as SalesChannelCode;
use App\Jobs\Search\SyncProductToSearchJob;
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

        // The products observer only fires on Product writes. Channel membership
        // lives in the pivot, so without this a product published to punchout
        // stays invisible to Typesense-backed punchout search until a full
        // reindex.
        if (config('typesense.enabled', false) && config('typesense.api_key') !== '') {
            dispatch(new SyncProductToSearchJob((int) $product->id, 'upsert'));
        }
    }
}
