<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\KnowledgeSourceEnum;
use App\Enums\KnowledgeStatusEnum;
use App\Enums\PostStatusEnum;
use App\Enums\PostTypeEnum;
use App\Models\KnowledgeEntry;
use App\Models\Post;
use App\Models\Tutorial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Fills the knowledge base from content the storefront already publishes.
 *
 * Derived entries are always created as **drafts**: model- or CMS-sourced text
 * is a starting point, not an approved answer, and a human publishes it in the
 * admin portal. Two rules keep that safe:
 *
 * 1. An entry a human has touched (status != draft) is never overwritten.
 * 2. A derived draft whose source is no longer public is removed.
 */
class KnowledgeIngestionService
{
    /**
     * Post types the storefront serves publicly, and their route prefix.
     * Mirrors SeoIndexingObserver's definition of a live public URL.
     */
    private const POST_ROUTES = [
        PostTypeEnum::BLOG->value => '/blogs',
        PostTypeEnum::NEWS->value => '/news',
        PostTypeEnum::EVENT->value => '/events',
    ];

    /** Derived entries are traced back to their source through this map. */
    private const SOURCE_MODELS = [
        KnowledgeSourceEnum::POST->value => Post::class,
        KnowledgeSourceEnum::TUTORIAL->value => Tutorial::class,
    ];

    public function __construct(
        protected KnowledgeEntryService $knowledgeEntryService,
    ) {}

    /**
     * @return array{posts: int, tutorials: int, removed: int}
     */
    public function ingestAll(?int $limit = null, bool $prune = true): array
    {
        return [
            'posts' => $this->ingestPosts($limit),
            'tutorials' => $this->ingestTutorials($limit),
            'removed' => $prune ? $this->prune() : 0,
        ];
    }

    public function ingestPosts(?int $limit = null): int
    {
        return $this->run(
            Post::query()
                ->where('status', PostStatusEnum::PUBLISHED->value)
                ->where('is_published_as_blog', true)
                ->whereIn('type', array_keys(self::POST_ROUTES))
                ->with('tags'),
            $limit,
            fn (Post $post) => $this->ingestPost($post)
        );
    }

    public function ingestTutorials(?int $limit = null): int
    {
        return $this->run(
            Tutorial::query()->published()->with('tags'),
            $limit,
            fn (Tutorial $tutorial) => $this->ingestTutorial($tutorial)
        );
    }

    /**
     * Remove derived drafts whose source is gone or no longer public.
     */
    public function prune(): int
    {
        $removed = 0;

        KnowledgeEntry::query()
            ->whereIn('source_type', array_keys(self::SOURCE_MODELS))
            ->where('status', KnowledgeStatusEnum::DRAFT->value)
            ->chunkById(200, function (Collection $entries) use (&$removed): void {
                foreach ($entries as $entry) {
                    if ($this->sourceIsLive($entry)) {
                        continue;
                    }

                    $this->knowledgeEntryService->delete($entry);
                    $removed++;
                }
            });

        return $removed;
    }

    protected function ingestPost(Post $post): bool
    {
        $route = self::POST_ROUTES[$post->type?->value ?? ''] ?? null;

        if ($route === null || empty($post->slug)) {
            return false;
        }

        return $this->upsert(KnowledgeSourceEnum::POST, $post, [
            'question' => null,
            'title' => trim((string) $post->title),
            'body' => $this->plainText($post),
            'source_url' => $route.'/'.$post->slug,
            'tags' => $this->tagNames($post),
            'locale' => '*',
        ]);
    }

    protected function ingestTutorial(Tutorial $tutorial): bool
    {
        return $this->upsert(KnowledgeSourceEnum::TUTORIAL, $tutorial, [
            'question' => null,
            'title' => trim((string) $tutorial->title),
            'body' => $this->plainText($tutorial),
            // The storefront serves no tutorial pages yet, so there is nothing
            // to cite. The text is still useful as an answer.
            'source_url' => null,
            'tags' => $this->tagNames($tutorial),
            'locale' => '*',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function upsert(KnowledgeSourceEnum $type, Model $source, array $attributes): bool
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        $body = trim((string) ($attributes['body'] ?? ''));

        if ($title === '' || $body === '') {
            return false;
        }

        $entry = KnowledgeEntry::query()
            ->where('source_type', $type->value)
            ->where('source_id', (int) $source->getKey())
            ->first();

        // A human has curated or published this derivation — never overwrite it.
        if ($entry && $entry->status !== KnowledgeStatusEnum::DRAFT) {
            return false;
        }

        $payload = [
            ...$attributes,
            'title' => $title,
            'body' => mb_substr($body, 0, 100000),
            'source_type' => $type->value,
            'source_id' => (int) $source->getKey(),
            'tenant_id' => null,
            'status' => KnowledgeStatusEnum::DRAFT->value,
        ];

        if ($entry) {
            $this->knowledgeEntryService->update($entry, $payload);
        } else {
            $this->knowledgeEntryService->create($payload);
        }

        return true;
    }

    protected function sourceIsLive(KnowledgeEntry $entry): bool
    {
        $modelClass = self::SOURCE_MODELS[$entry->source_type?->value ?? ''] ?? null;

        if ($modelClass === null) {
            return true;
        }

        $source = $modelClass::query()->find($entry->source_id);

        if ($source instanceof Post) {
            return $source->status === PostStatusEnum::PUBLISHED
                && (bool) $source->is_published_as_blog
                && array_key_exists($source->type?->value ?? '', self::POST_ROUTES);
        }

        if ($source instanceof Tutorial) {
            return $source->isPublished();
        }

        return false;
    }

    /**
     * Iterate a source query in batches, stopping once `limit` rows are scanned.
     *
     * @param  callable(Model): bool  $handler
     */
    protected function run(Builder $query, ?int $limit, callable $handler): int
    {
        $ingested = 0;
        $scanned = 0;
        $exhausted = false;

        $query->chunkById(200, function (Collection $rows) use (&$ingested, &$scanned, &$exhausted, $limit, $handler) {
            foreach ($rows as $row) {
                if ($limit !== null && $scanned >= $limit) {
                    $exhausted = true;

                    break;
                }

                $scanned++;

                if ($handler($row)) {
                    $ingested++;
                }
            }

            // Returning false stops the chunk iteration.
            return $exhausted ? false : null;
        });

        return $ingested;
    }

    /**
     * Flatten the source's HTML into the plain text the assistant answers from.
     */
    protected function plainText(Model $source): string
    {
        $parts = array_filter([
            trim((string) ($source->excerpt ?? '')),
            trim((string) ($source->content ?? '')),
        ]);

        $text = strip_tags(implode("\n\n", $parts));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * @return array<int, string>
     */
    protected function tagNames(Model $source): array
    {
        if (! $source->relationLoaded('tags')) {
            return [];
        }

        return $source->tags
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values()
            ->all();
    }
}
