<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Actions\Knowledge\KnowledgeEntryCreateAction;
use App\Actions\Knowledge\KnowledgeEntryDeleteAction;
use App\Actions\Knowledge\KnowledgeEntryUpdateAction;
use App\Helpers\CacheHelper;
use App\Models\KnowledgeEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Business logic for the knowledge base.
 *
 * Indexing itself is handled by KnowledgeEntryObserver → IndexKnowledgeEntryJob,
 * so writes never block on Typesense. Caches are cleared here so a freshly
 * published entry is immediately visible to retrieval.
 */
class KnowledgeEntryService
{
    public function __construct(
        protected KnowledgeEntryCreateAction $createAction,
        protected KnowledgeEntryUpdateAction $updateAction,
        protected KnowledgeEntryDeleteAction $deleteAction,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): KnowledgeEntry
    {
        $entry = $this->createAction->handle($data);

        $this->clearCaches();

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(KnowledgeEntry $entry, array $data): KnowledgeEntry
    {
        $entry = $this->updateAction->handle($entry, $data);

        $this->clearCaches();

        return $entry;
    }

    public function delete(KnowledgeEntry $entry): bool
    {
        $deleted = $this->deleteAction->handle($entry);

        $this->clearCaches();

        return $deleted;
    }

    /**
     * Admin listing query.
     *
     * @param  array{search?: ?string, status?: ?string, locale?: ?string, sort?: ?string, order?: ?string}  $filters
     */
    public function search(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $locale = $filters['locale'] ?? null;

        $sort = in_array($filters['sort'] ?? null, ['title', 'status', 'priority', 'created_at', 'updated_at'], true)
            ? (string) $filters['sort']
            : 'updated_at';

        $order = ($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return KnowledgeEntry::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('question', 'like', $term)
                        ->orWhere('body', 'like', $term);
                });
            })
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($locale, fn (Builder $query) => $query->where('locale', $locale))
            ->orderBy($sort, $order);
    }

    /**
     * Counts for the admin dashboard.
     *
     * @return array<string, int>
     */
    public function statistics(): array
    {
        $counts = KnowledgeEntry::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return [
            'total' => array_sum($counts),
            'published' => (int) ($counts['published'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
            'archived' => (int) ($counts['archived'] ?? 0),
        ];
    }

    protected function clearCaches(): void
    {
        CacheHelper::clearTags(KnowledgeSearchService::CACHE_TAGS);
    }
}
