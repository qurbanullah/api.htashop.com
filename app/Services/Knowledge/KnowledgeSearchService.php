<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\KnowledgeStatusEnum;
use App\Helpers\CacheHelper;
use App\Interfaces\Ai\KnowledgeRetrieverInterface;
use App\Models\KnowledgeEntry;
use App\Services\Ai\ProviderManager;
use App\Support\Ai\RetrievedPassage;
use Illuminate\Support\Collection;
use Throwable;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

/**
 * Indexes knowledge entries into Typesense and retrieves grounding passages.
 *
 * Mirrors ProductSearchService: same client wiring, redis-backed caching, and
 * a database fallback so the assistant still answers if Typesense is down.
 * When embeddings are configured, search runs as a hybrid (keyword + vector)
 * query fused by `alpha`.
 */
class KnowledgeSearchService implements KnowledgeRetrieverInterface
{
    public const CACHE_TAGS = ['knowledge'];

    /** Sentinel locale/tenant values for "applies to everyone". */
    public const LOCALE_ALL = 'all';

    public const GLOBAL_TENANT = 0;

    protected ?Client $client = null;

    public function __construct(
        protected ProviderManager $providerManager,
        protected KnowledgeChunker $chunker,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('typesense.enabled', false) && config('typesense.api_key') !== '';
    }

    /*
    |--------------------------------------------------------------------------
    | Index management
    |--------------------------------------------------------------------------
    */

    /**
     * Create the knowledge collection if absent, adding any new fields.
     */
    public function ensureCollection(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $existing = $this->client()->collections[$this->collectionName()]->retrieve();
            $existingFields = array_column($existing['fields'] ?? [], 'name');

            $missing = array_values(array_filter(
                $this->schema()['fields'],
                fn (array $field) => ! in_array($field['name'], $existingFields, true)
            ));

            if (! empty($missing)) {
                $this->client()->collections[$this->collectionName()]->update(['fields' => $missing]);
            }
        } catch (ObjectNotFound) {
            $this->client()->collections->create($this->schema());
        }
    }

    public function dropCollection(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $this->client()->collections[$this->collectionName()]->delete();
        } catch (ObjectNotFound) {
            // Already absent.
        }
    }

    /**
     * Chunk, embed and upsert a single entry. Unpublished entries are removed.
     */
    public function indexEntry(KnowledgeEntry $entry): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if ($entry->status !== KnowledgeStatusEnum::PUBLISHED) {
            $this->deleteEntry((int) $entry->id);

            return;
        }

        $this->ensureCollection();
        $this->deleteEntry((int) $entry->id);

        $chunks = $this->chunker->chunk($entry->searchableText());

        if ($chunks === []) {
            return;
        }

        $vectors = $this->embedChunks($chunks);
        $documents = [];

        foreach ($chunks as $index => $chunk) {
            $document = [
                'id' => $entry->id.'-'.$chunk['position'],
                'entry_id' => (int) $entry->id,
                'entry_uuid' => (string) $entry->uuid,
                'title' => (string) $entry->title,
                'question' => (string) ($entry->question ?? ''),
                'content' => $chunk['content'],
                'chunk_position' => (int) $chunk['position'],
                'source_url' => (string) ($entry->source_url ?? ''),
                'source_type' => $entry->source_type?->value ?? 'manual',
                'tags' => array_values((array) ($entry->tags ?? [])),
                'locale' => $entry->locale === '*' ? self::LOCALE_ALL : (string) $entry->locale,
                'tenant_id' => (int) ($entry->tenant_id ?? self::GLOBAL_TENANT),
                'status' => KnowledgeStatusEnum::PUBLISHED->value,
                'restricted' => (bool) $entry->restricted,
                'priority' => (int) $entry->priority,
                'updated_at' => (int) ($entry->updated_at?->getTimestamp() ?? now()->getTimestamp()),
            ];

            if (isset($vectors[$index])) {
                $document['embedding'] = $vectors[$index];
            }

            $documents[] = $document;
        }

        $this->client()
            ->collections[$this->collectionName()]
            ->getDocuments()
            ->import($documents, ['action' => 'upsert']);

        CacheHelper::clearTags(self::CACHE_TAGS);
    }

    /**
     * Remove every chunk belonging to an entry.
     */
    public function deleteEntry(int $entryId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $this->client()
                ->collections[$this->collectionName()]
                ->getDocuments()
                ->delete(['filter_by' => 'entry_id:='.$entryId]);
        } catch (ObjectNotFound) {
            // Collection does not exist yet.
        } catch (Throwable $exception) {
            logger()->warning('Failed to remove knowledge chunks', [
                'entry_id' => $entryId,
                'error' => $exception->getMessage(),
            ]);
        }

        CacheHelper::clearTags(self::CACHE_TAGS);
    }

    /**
     * Reindex every published entry. Returns the number indexed.
     */
    public function reindexAll(int $chunkSize = 200): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $this->ensureCollection();

        $count = 0;

        KnowledgeEntry::query()
            ->published()
            ->chunkById($chunkSize, function (Collection $entries) use (&$count): void {
                foreach ($entries as $entry) {
                    $this->indexEntry($entry);
                    $count++;
                }
            });

        CacheHelper::clearTags(self::CACHE_TAGS);

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, RetrievedPassage>
     */
    public function retrieve(string $query, ?string $locale = null, int $limit = 5): array
    {
        $limit = max(1, $limit);

        if (! $this->isEnabled()) {
            return $this->fallbackSearch($query, $locale, $limit);
        }

        try {
            $significant = $this->significantQuery($query);

            $parameters = [
                'q' => $significant !== '' ? $significant : '*',
                'query_by' => 'title,question,content,tags',
                // Weigh the curated title/question/tags above free-text body, so a
                // verbose entry repeating common words cannot outrank a precise one.
                'query_by_weights' => '3,3,1,2',
                // Rank by how many distinct query terms matched (field-weighted)
                // rather than by raw term frequency.
                'text_match_type' => 'max_weight',
                'filter_by' => $this->filterBy($locale),
                'limit' => $limit,
                'include_fields' => 'entry_id,entry_uuid,title,content,source_url,source_type,restricted,priority',
            ];

            $vector = $this->queryVector($query);

            if ($vector !== null) {
                $parameters['vector_query'] = 'embedding:('.implode(',', $vector)
                    .', k:'.max($limit * 3, 20).')';
                $parameters['alpha'] = (float) config('ai.retrieval.alpha', 0.3);
            }

            $response = $this->client()
                ->collections[$this->collectionName()]
                ->getDocuments()
                ->search($parameters);

            return $this->mapHits($response['hits'] ?? []);
        } catch (Throwable $exception) {
            logger()->warning('Knowledge retrieval failed; using database fallback', [
                'error' => $exception->getMessage(),
            ]);

            return $this->fallbackSearch($query, $locale, $limit);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $hits
     * @return array<int, RetrievedPassage>
     */
    protected function mapHits(array $hits): array
    {
        $passages = [];

        foreach ($hits as $hit) {
            $document = $hit['document'] ?? [];
            $sourceUrl = (string) ($document['source_url'] ?? '');

            $passages[] = new RetrievedPassage(
                entryId: (int) ($document['entry_id'] ?? 0),
                entryUuid: (string) ($document['entry_uuid'] ?? ''),
                title: (string) ($document['title'] ?? ''),
                content: (string) ($document['content'] ?? ''),
                sourceUrl: $sourceUrl !== '' ? $sourceUrl : null,
                sourceType: $document['source_type'] ?? null,
                score: (float) ($hit['hybrid_search_info']['rank_fusion_score']
                    ?? $hit['text_match']
                    ?? $hit['vector_distance']
                    ?? 0),
                restricted: (bool) ($document['restricted'] ?? false),
            );
        }

        return $passages;
    }

    /**
     * Function words that carry no retrieval signal. Without filtering them, a
     * query like "what are the delivery methods" matches almost every entry on
     * "the", and the fallback ends up ranking by priority alone.
     *
     * @var array<int, string>
     */
    private const STOP_WORDS = [
        'a', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'but', 'by', 'can', 'do', 'does', 'for',
        'from', 'had', 'has', 'have', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'me', 'my',
        'no', 'not', 'of', 'on', 'or', 'our', 'so', 'that', 'the', 'their', 'them', 'then', 'there',
        'these', 'they', 'this', 'to', 'us', 'was', 'we', 'were', 'what', 'when', 'where', 'which',
        'who', 'why', 'will', 'with', 'you', 'your',
    ];

    /**
     * Keyword search straight from the database when Typesense is unavailable.
     *
     * Ranks by how many distinct query terms matched, then by curator priority,
     * so a single shared common word cannot outrank a genuinely relevant entry.
     *
     * @return array<int, RetrievedPassage>
     */
    protected function fallbackSearch(string $query, ?string $locale, int $limit): array
    {
        $terms = $this->terms($query);

        $builder = KnowledgeEntry::query()
            ->published()
            ->when($locale, fn ($queryBuilder) => $queryBuilder->forLocale($locale));

        if ($terms === []) {
            return $builder
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (KnowledgeEntry $entry) => $this->toPassage($entry))
                ->all();
        }

        $builder->where(function ($inner) use ($terms): void {
            foreach ($terms as $term) {
                $like = $this->likePattern($term);

                $inner->orWhere('title', 'like', $like)
                    ->orWhere('question', 'like', $like)
                    ->orWhere('body', 'like', $like);
            }
        });

        // Score each row by how many distinct terms it contains.
        $scoreParts = [];
        $bindings = [];

        foreach ($terms as $term) {
            $like = $this->likePattern($term);

            $scoreParts[] = '(case when (title like ? or question like ? or body like ?) then 1 else 0 end)';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        return $builder
            ->selectRaw('knowledge_entries.*, ('.implode(' + ', $scoreParts).') as term_matches', $bindings)
            ->orderByDesc('term_matches')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (KnowledgeEntry $entry) => $this->toPassage($entry))
            ->all();
    }

    /**
     * Strip function words before handing a query to the engine.
     *
     * Typesense drops the *rarest* tokens first when a search returns too few
     * results, so "what is your phone number" loses "phone" and "number" and
     * then matches entries on "is"/"your" instead. Removing the function words
     * up front leaves the intent-bearing tokens, and keeps both retrieval paths
     * (Typesense and the database fallback) agreeing on what matters.
     */
    protected function significantQuery(string $query): string
    {
        return implode(' ', $this->terms($query));
    }

    protected function likePattern(string $term): string
    {
        return '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
    }

    protected function toPassage(KnowledgeEntry $entry): RetrievedPassage
    {
        return new RetrievedPassage(
            entryId: (int) $entry->id,
            entryUuid: (string) $entry->uuid,
            title: (string) $entry->title,
            content: (string) $entry->body,
            sourceUrl: $entry->source_url,
            sourceType: $entry->source_type?->value,
            score: 0.0,
            restricted: (bool) $entry->restricted,
        );
    }

    /**
     * Significant keywords from a natural-language query, for the keyword
     * fallback.
     *
     * @return array<int, string>
     */
    protected function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [];

        $words = array_values(array_filter(
            array_unique($words),
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::STOP_WORDS, true)
        ));

        return array_slice($words, 0, 8);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    protected function filterBy(?string $locale): string
    {
        $locales = $locale ? array_unique([$locale, self::LOCALE_ALL]) : [self::LOCALE_ALL];
        $tenants = [self::GLOBAL_TENANT];

        return implode(' && ', [
            'status:='.KnowledgeStatusEnum::PUBLISHED->value,
            'locale:=['.implode(',', $locales).']',
            'tenant_id:=['.implode(',', $tenants).']',
        ]);
    }

    /**
     * Embed the query, cached — identical questions are extremely common.
     *
     * @return array<int, float>|null
     */
    protected function queryVector(string $query): ?array
    {
        $query = trim($query);

        if ($query === '' || ! $this->embeddingsEnabled()) {
            return null;
        }

        $embeddings = $this->providerManager->embeddings();
        $cacheKey = 'chat:qvec:'.md5($embeddings->model().'|'.mb_strtolower($query));

        try {
            $vector = CacheHelper::remember(
                self::CACHE_TAGS,
                $cacheKey,
                (int) config('ai.embeddings.query_cache_seconds', 900),
                fn () => $embeddings->embed([$query])[0] ?? null
            );

            return is_array($vector) && $vector !== [] ? $vector : null;
        } catch (Throwable $exception) {
            logger()->warning('Query embedding failed', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int, array{content: string, position: int}>  $chunks
     * @return array<int, array<int, float>>
     */
    protected function embedChunks(array $chunks): array
    {
        if (! $this->embeddingsEnabled()) {
            return [];
        }

        $texts = array_map(fn (array $chunk) => $chunk['content'], $chunks);

        try {
            return $this->providerManager->embeddings()->embed($texts);
        } catch (Throwable $exception) {
            logger()->warning('Knowledge chunk embedding failed', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    protected function embeddingsEnabled(): bool
    {
        $embeddings = $this->providerManager->embeddings();

        return $embeddings->isEnabled() && $embeddings->dimensions() > 0;
    }

    protected function collectionName(): string
    {
        return (string) config('ai.retrieval.collection', 'knowledge');
    }

    protected function client(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        return $this->client = new Client([
            'api_key' => (string) config('typesense.api_key'),
            'nodes' => [[
                'host' => (string) config('typesense.host'),
                'port' => (string) config('typesense.port'),
                'protocol' => (string) config('typesense.protocol'),
            ]],
            'connection_timeout_seconds' => (float) config('typesense.connect_timeout_seconds', 3),
            'healthcheck_interval_seconds' => (int) config('typesense.healthcheck_interval_seconds', 60),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        $fields = [
            ['name' => 'entry_id', 'type' => 'int32'],
            ['name' => 'entry_uuid', 'type' => 'string'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'question', 'type' => 'string', 'optional' => true],
            ['name' => 'content', 'type' => 'string'],
            ['name' => 'chunk_position', 'type' => 'int32'],
            ['name' => 'source_url', 'type' => 'string', 'optional' => true],
            ['name' => 'source_type', 'type' => 'string', 'optional' => true],
            ['name' => 'tags', 'type' => 'string[]', 'optional' => true],
            ['name' => 'locale', 'type' => 'string', 'facet' => true],
            ['name' => 'tenant_id', 'type' => 'int32', 'facet' => true],
            ['name' => 'status', 'type' => 'string', 'facet' => true],
            ['name' => 'restricted', 'type' => 'bool'],
            ['name' => 'priority', 'type' => 'int32'],
            ['name' => 'updated_at', 'type' => 'int64'],
        ];

        if ($this->embeddingsEnabled()) {
            $fields[] = [
                'name' => 'embedding',
                'type' => 'float[]',
                'optional' => true,
                'num_dim' => $this->providerManager->embeddings()->dimensions(),
            ];
        }

        return [
            'name' => $this->collectionName(),
            'fields' => $fields,
            'default_sorting_field' => 'priority',
        ];
    }
}
