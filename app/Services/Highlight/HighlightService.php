<?php

namespace App\Services\Highlight;

use App\Actions\Highlight\HighlightCreateAction;
use App\Actions\Highlight\HighlightDeleteAction;
use App\Actions\Highlight\HighlightListAction;
use App\Actions\Highlight\HighlightReadProductAction;
use App\Actions\Highlight\HighlightSyncProductAction;
use App\Actions\Highlight\HighlightUpdateAction;
use App\Models\Category;
use App\Models\Highlight;
use Illuminate\Pagination\LengthAwarePaginator;

class HighlightService
{
    public function __construct(
        protected HighlightListAction $listAction,
        protected HighlightCreateAction $createAction,
        protected HighlightUpdateAction $updateAction,
        protected HighlightDeleteAction $deleteAction,
        protected HighlightReadProductAction $readProductAction,
        protected HighlightSyncProductAction $syncProductAction,
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        // Global highlights are shared with every tenant. Merchant-created
        // highlights are isolated to their tenant. Admins see everything.
        $user = auth()->user();
        if (! $user?->hasRole(['super-admin', 'admin'])) {
            $filters['scope_tenant'] = true;
            $filters['tenant_id'] = $this->resolveTenantId();
        }

        return $this->listAction->handle($filters);
    }

    public function available(array $filters = []): LengthAwarePaginator
    {
        // The manage picker may pass a deep category (e.g. Laptops & Notebooks).
        // Resolve it to its top-level branch so highlights seeded on sibling
        // sub-categories are surfaced to merchants.
        if (! empty($filters['category_ids'])) {
            $filters['category_ids'] = $this->resolveCategoryBranchIds($filters['category_ids']);
        }

        return $this->list($filters);
    }

    public function create(array $data): Highlight
    {
        return $this->createAction->handle($data);
    }

    public function createMerchant(array $data): Highlight
    {
        $data['tenant_id'] = $data['tenant_id'] ?? $this->resolveTenantId();

        return $this->createAction->handle($data);
    }

    private function resolveTenantId(): ?int
    {
        return auth()->user()?->memberships()->where('is_active', true)->first()?->tenant_id;
    }

    /**
     * Expand the requested category ids to every category in the same top-level
     * branch. Highlights are curated per sub-category, but the manage picker
     * should surface relevant highlights across the whole branch.
     */
    private function resolveCategoryBranchIds(array $categoryIds): array
    {
        $topLevelIds = [];

        foreach (array_map('intval', $categoryIds) as $categoryId) {
            $category = Category::query()->find($categoryId);
            while ($category && $category->parent_id) {
                $category = $category->parent;
            }
            if ($category) {
                $topLevelIds[] = $category->id;
            }
        }

        if (empty($topLevelIds)) {
            return [];
        }

        $allIds = [];
        foreach (array_unique($topLevelIds) as $topLevelId) {
            $this->collectDescendantIds($topLevelId, $allIds);
        }

        return array_values(array_unique($allIds));
    }

    private function collectDescendantIds(int $parentId, array &$ids): void
    {
        $ids[] = $parentId;

        $children = Category::query()->where('parent_id', $parentId)->pluck('id');
        foreach ($children as $childId) {
            $this->collectDescendantIds($childId, $ids);
        }
    }

    public function update(Highlight $highlight, array $data): Highlight
    {
        return $this->updateAction->handle($highlight, $data);
    }

    public function delete(Highlight $highlight): void
    {
        $this->deleteAction->handle($highlight);
    }

    public function productHighlights(string $uuid): array
    {
        return $this->readProductAction->handle($uuid);
    }

    public function syncProduct(string $uuid, array $items): array
    {
        $this->syncProductAction->handle($uuid, $items);

        return $this->readProductAction->handle($uuid);
    }
}
