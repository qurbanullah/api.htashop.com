<?php

namespace Database\Seeders;

use App\Enums\SalesChannel as SalesChannelCode;
use App\Models\Product;
use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class ProductSalesChannelSeeder extends Seeder
{
    /**
     * Attach the storefront channel to products created by seeders (which
     * bypass ProductCreateAction). Punchout membership remains opt-in.
     */
    public function run(): void
    {
        $storefrontId = SalesChannel::query()
            ->where('code', SalesChannelCode::STOREFRONT)
            ->value('id');

        if (! $storefrontId) {
            return;
        }

        Product::query()
            ->whereDoesntHave('salesChannels')
            ->chunkById(500, function ($products) use ($storefrontId): void {
                foreach ($products as $product) {
                    $product->salesChannels()->syncWithoutDetaching([$storefrontId]);
                }
            });
    }
}
