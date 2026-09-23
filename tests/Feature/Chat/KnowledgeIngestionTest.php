<?php

use App\Enums\KnowledgeStatusEnum;
use App\Enums\PostStatusEnum;
use App\Enums\PostTypeEnum;
use App\Enums\TutorialStatusEnum;
use App\Models\KnowledgeEntry;
use App\Models\Post;
use App\Models\Tutorial;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEntryService;
use App\Services\Knowledge\KnowledgeIngestionService;

beforeEach(function () {
    // Keep the IndexNow observe->dispatch path off the network in tests.
    config(['indexnow.enabled' => false]);
});

/** Posts and tutorials require an author. */
function contentCreatorId(): int
{
    return User::factory()->create()->id;
}

function makeBlogPost(array $attributes = []): Post
{
    return Post::create(array_merge([
        'title' => 'How to choose a drill',
        'slug' => 'how-to-choose-a-drill',
        'excerpt' => 'A short summary.',
        'content' => '<p>Long <strong>content</strong> about drills.</p>',
        'type' => PostTypeEnum::BLOG->value,
        'status' => PostStatusEnum::PUBLISHED->value,
        'is_published_as_blog' => true,
        'created_by' => contentCreatorId(),
    ], $attributes));
}

it('creates a draft entry from a published blog post', function () {
    makeBlogPost();

    $ingested = app(KnowledgeIngestionService::class)->ingestPosts();

    expect($ingested)->toBe(1);

    $entry = KnowledgeEntry::query()->where('source_type', 'post')->firstOrFail();

    expect($entry->status)->toBe(KnowledgeStatusEnum::DRAFT)
        ->and($entry->source_url)->toBe('/blogs/how-to-choose-a-drill')
        ->and($entry->locale)->toBe('*')
        ->and($entry->body)->toContain('Long content about drills')
        ->and($entry->body)->not->toContain('<strong>');
});

it('refreshes a derived draft when the source changes', function () {
    $post = makeBlogPost();
    $service = app(KnowledgeIngestionService::class);

    $service->ingestPosts();
    $post->update(['content' => '<p>Updated drill guidance.</p>']);
    $service->ingestPosts();

    $entry = KnowledgeEntry::query()->where('source_type', 'post')->firstOrFail();

    expect($entry->body)->toContain('Updated drill guidance')
        ->and(KnowledgeEntry::query()->where('source_type', 'post')->count())->toBe(1);
});

it('never overwrites an entry a human has published', function () {
    $post = makeBlogPost();
    $ingestion = app(KnowledgeIngestionService::class);

    $ingestion->ingestPosts();

    $entry = KnowledgeEntry::query()->where('source_type', 'post')->firstOrFail();
    app(KnowledgeEntryService::class)->update($entry, [
        'status' => KnowledgeStatusEnum::PUBLISHED->value,
        'body' => 'Curated answer written by a human.',
    ]);

    $post->update(['content' => '<p>Automated text that must not win.</p>']);
    $ingestion->ingestPosts();

    expect($entry->fresh()->body)->toBe('Curated answer written by a human.');
});

it('removes the derived draft when the source is unpublished', function () {
    $post = makeBlogPost();
    $ingestion = app(KnowledgeIngestionService::class);

    $ingestion->ingestPosts();
    expect(KnowledgeEntry::query()->where('source_type', 'post')->count())->toBe(1);

    $post->update(['status' => PostStatusEnum::DRAFT->value]);
    $removed = $ingestion->prune();

    expect($removed)->toBe(1)
        ->and(KnowledgeEntry::query()->where('source_type', 'post')->count())->toBe(0);
});

it('ignores post types the storefront does not serve publicly', function () {
    makeBlogPost([
        'title' => 'Internal newsletter',
        'slug' => 'internal-newsletter',
        'type' => PostTypeEnum::NEWSLETTER->value,
    ]);

    expect(app(KnowledgeIngestionService::class)->ingestPosts())->toBe(0);
});

it('ignores a published post that is not published as a blog', function () {
    makeBlogPost(['is_published_as_blog' => false]);

    expect(app(KnowledgeIngestionService::class)->ingestPosts())->toBe(0);
});

it('ingests published tutorials without a citation link', function () {
    Tutorial::create([
        'title' => 'Setting up your account',
        'slug' => 'setting-up-your-account',
        'excerpt' => 'Getting started.',
        'content' => '<p>Step by step setup.</p>',
        'status' => TutorialStatusEnum::PUBLISHED->value,
        'published_at' => now()->subDay(),
        'created_by' => contentCreatorId(),
    ]);

    $ingested = app(KnowledgeIngestionService::class)->ingestTutorials();

    expect($ingested)->toBe(1);

    $entry = KnowledgeEntry::query()->where('source_type', 'tutorial')->firstOrFail();

    expect($entry->source_url)->toBeNull()
        ->and($entry->status)->toBe(KnowledgeStatusEnum::DRAFT)
        ->and($entry->body)->toContain('Step by step setup');
});

it('skips sources with no usable text', function () {
    makeBlogPost(['content' => '', 'excerpt' => '']);

    expect(app(KnowledgeIngestionService::class)->ingestPosts())->toBe(0);
});
