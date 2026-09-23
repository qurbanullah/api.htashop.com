<?php

use App\Interfaces\Ai\KnowledgeRetrieverInterface;
use App\Models\KnowledgeEntry;
use App\Services\Knowledge\KnowledgeEntryService;

it('retrieves published entries through the database fallback', function () {
    KnowledgeEntry::create([
        'title' => 'Returns policy',
        'body' => 'You can return most items within the window described in the returns policy.',
        'status' => 'published',
        'locale' => '*',
    ]);

    $retriever = app(KnowledgeRetrieverInterface::class);
    $results = $retriever->retrieve('returns', 'en', 5);

    expect($results)->toHaveCount(1)
        ->and($results[0]->title)->toBe('Returns policy');
});

it('never surfaces unpublished entries', function () {
    KnowledgeEntry::create([
        'title' => 'Secret draft',
        'body' => 'This must not be retrievable until it is published.',
        'status' => 'draft',
        'locale' => '*',
    ]);

    $retriever = app(KnowledgeRetrieverInterface::class);

    expect($retriever->retrieve('Secret draft', 'en', 5))->toBe([]);
});

it('matches a locale-agnostic entry for any locale', function () {
    KnowledgeEntry::create([
        'title' => 'Contact us',
        'body' => 'Reach the team from the contact page.',
        'status' => 'published',
        'locale' => '*',
    ]);

    $retriever = app(KnowledgeRetrieverInterface::class);

    expect($retriever->retrieve('contact', 'de', 5))->toHaveCount(1)
        ->and($retriever->retrieve('contact', 'ur', 5))->toHaveCount(1);
});

it('publishes entries created through the service', function () {
    $entry = app(KnowledgeEntryService::class)->create([
        'title' => 'Warranty information',
        'body' => 'Warranty terms are described in the warranty policy.',
        'status' => 'published',
        'locale' => '*',
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->uuid)->not->toBeNull()
        ->and($entry->published_at)->not->toBeNull()
        ->and($entry->tenant_id)->toBeNull();
});
