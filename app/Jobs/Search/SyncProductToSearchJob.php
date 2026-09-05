<?php

namespace App\Jobs\Search;

use App\Models\Product;
use App\Services\Search\ProductSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductToSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $productId,
        public string $action = 'upsert', // 'upsert' | 'delete'
    ) {
    }

    public function handle(ProductSearchService $searchService): void
    {
        if (! $searchService->isEnabled()) {
            return;
        }

        if ($this->action === 'delete') {
            $searchService->deleteFromIndex($this->productId);

            return;
        }

        $product = Product::with([
            'categories',
            'brands',
            'tags',
            'dams' => fn ($query) => $query
                ->where('collection_name', 'featured')
                ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                ->orderBy('sort_order'),
        ])->find($this->productId);

        if ($product) {
            $searchService->indexProduct($product);
        }
    }
}
