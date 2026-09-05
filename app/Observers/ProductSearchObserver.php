<?php

namespace App\Observers;

use App\Jobs\Search\SyncProductToSearchJob;
use App\Models\Product;

/**
 * Keeps the Typesense products index in sync with the products table.
 * Sync is dispatched on the queue so product writes are never blocked
 * by the search engine.
 */
class ProductSearchObserver
{
    public function saved(Product $product): void
    {
        if (! $this->searchEnabled()) {
            return;
        }

        dispatch(new SyncProductToSearchJob((int) $product->id, 'upsert'));
    }

    public function deleted(Product $product): void
    {
        if (! $this->searchEnabled()) {
            return;
        }

        dispatch(new SyncProductToSearchJob((int) $product->id, 'delete'));
    }

    public function restored(Product $product): void
    {
        if (! $this->searchEnabled()) {
            return;
        }

        dispatch(new SyncProductToSearchJob((int) $product->id, 'upsert'));
    }

    protected function searchEnabled(): bool
    {
        return config('typesense.enabled', false) && config('typesense.api_key') !== '';
    }
}
