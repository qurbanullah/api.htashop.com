<?php

use App\Interfaces\Ai\KnowledgeRetrieverInterface;
use App\Models\KnowledgeEntry;
use Database\Seeders\KnowledgeEntrySeeder;
use Illuminate\Support\Str;

it('ships a well-formed seed corpus', function () {
    $entries = config('knowledge.seed');

    expect($entries)->toBeArray()->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry['title'] ?? null)->toBeString()->not->toBeEmpty()
            ->and($entry['question'] ?? null)->toBeString()->not->toBeEmpty()
            ->and($entry['body'] ?? null)->toBeString()->not->toBeEmpty()
            ->and($entry['tags'] ?? null)->toBeArray()->not->toBeEmpty();

        // Citations must be site-relative paths the storefront can serve.
        expect($entry['source_url'] ?? null)->toBeString()->toStartWith('/');
    }
});

it('keeps seed slugs unique so re-seeding updates instead of duplicating', function () {
    $slugs = array_map(
        fn (array $entry) => Str::slug((string) $entry['title']),
        (array) config('knowledge.seed')
    );

    expect($slugs)->toBe(array_values(array_unique($slugs)));
});

it('publishes every seed entry', function () {
    $this->seed(KnowledgeEntrySeeder::class);

    expect(KnowledgeEntry::query()->count())->toBe(count((array) config('knowledge.seed')))
        ->and(KnowledgeEntry::query()->where('status', 'published')->count())
        ->toBe(count((array) config('knowledge.seed')));
});

it('makes the delivery-methods answer retrievable', function () {
    $this->seed(KnowledgeEntrySeeder::class);

    $results = app(KnowledgeRetrieverInterface::class)->retrieve('What are the delivery methods', 'en', 3);

    expect($results)->not->toBeEmpty()
        ->and($results[0]->title)->toBe('Delivery methods');
});
