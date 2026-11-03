<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * The category tree is cached under a handful of keys. Any write that changes
 * the tree (admin CRUD, seeders, maintenance commands) must flush them or the
 * storefront keeps serving the old hierarchy.
 */
class CategoryCache
{
    public static function flush(): void
    {
        Cache::forget('categories:all');
        Cache::forget('categories:tree');
        Cache::forget('catalog:top-nav:'.(int) config('catalog.top_nav.limit', 6));
    }
}
