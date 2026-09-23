<?php

use App\Services\Knowledge\KnowledgeChunker;

beforeEach(function () {
    config([
        'knowledge.chunk.max_characters' => 300,
        'knowledge.chunk.overlap_characters' => 50,
        'knowledge.chunk.min_characters' => 10,
    ]);
});

it('returns no chunks for empty text', function () {
    expect((new KnowledgeChunker())->chunk('   '))->toBe([]);
});

it('keeps short text as a single chunk', function () {
    $chunks = (new KnowledgeChunker())->chunk('A short answer about shipping.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['content'])->toBe('A short answer about shipping.')
        ->and($chunks[0]['position'])->toBe(0);
});

it('splits long text into sequential overlapping chunks', function () {
    $text = implode("\n\n", array_map(
        fn (int $index) => str_repeat("paragraph{$index} ", 12),
        range(1, 10)
    ));

    $chunks = (new KnowledgeChunker())->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $index => $chunk) {
        expect($chunk['position'])->toBe($index)
            ->and(mb_strlen($chunk['content']))->toBeLessThanOrEqual(300 + 50 + 10);
    }
});

it('hard-splits a single oversized paragraph', function () {
    $chunks = (new KnowledgeChunker())->chunk(str_repeat('word ', 400));

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk['content']))->toBeLessThanOrEqual(300 + 50 + 10);
    }
});

it('drops passages below the minimum length', function () {
    config(['knowledge.chunk.min_characters' => 100]);

    expect((new KnowledgeChunker())->chunk('too short'))->toBe([]);
});
