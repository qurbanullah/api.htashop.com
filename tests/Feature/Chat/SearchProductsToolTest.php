<?php

use App\Models\Conversation;
use App\Services\Ai\ToolRegistry;
use App\Services\Ai\Tools\SearchProductsTool;
use App\Services\Search\ProductSearchService;
use App\Support\Ai\ChatToolContext;
use Mockery\MockInterface;

function toolContext(): ChatToolContext
{
    $conversation = Conversation::create([
        'visitor_key' => hash('sha256', 'chat|'.uniqid('', true)),
        'locale' => 'en',
        'status' => 'open',
    ]);

    return new ChatToolContext($conversation, null, 'en');
}

function productHit(): array
{
    return [
        'name' => 'Cordless Drill 18V',
        'slug' => 'cordless-drill-18v',
        'route_key' => 'cordless-drill-18v-1a2b3c4d',
        'price' => 99.5,
        'sale_price' => 79.5,
        'currency' => 'EUR',
        'summary' => 'Compact cordless drill',
        'brands' => [['name' => 'Bosch']],
    ];
}

it('shapes products with a storefront link', function () {
    $service = Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('suggest')->once()->with('drill', 5)->andReturn([productHit()]);
    });

    $result = (new SearchProductsTool($service))->handle(['query' => 'drill'], toolContext());

    expect($result['products'])->toHaveCount(1)
        ->and($result['products'][0]['name'])->toBe('Cordless Drill 18V')
        ->and($result['products'][0]['url'])->toBe('/products/cordless-drill-18v-1a2b3c4d')
        ->and($result['products'][0]['price'])->toBe(99.5)
        ->and($result['products'][0]['sale_price'])->toBe(79.5)
        ->and($result['products'][0]['brand'])->toBe('Bosch');
});

it('does not query the catalogue for an empty search', function () {
    $service = Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('suggest')->never();
    });

    $result = (new SearchProductsTool($service))->handle(['query' => '   '], toolContext());

    expect($result)->toBe(['products' => []]);
});

it('refuses to search when the tool is disabled', function () {
    config(['ai.chat.products_enabled' => false]);

    $service = Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('suggest')->never();
    });

    $result = (new SearchProductsTool($service))->handle(['query' => 'drill'], toolContext());

    expect($result)->toHaveKey('error');
});

it('falls back to the database when the search engine throws', function () {
    $service = Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('suggest')->once()->andThrow(new RuntimeException('typesense down'));
        $mock->shouldReceive('fallbackSuggest')->once()->with('drill', 5)->andReturn([productHit()]);
    });

    $result = (new SearchProductsTool($service))->handle(['query' => 'drill'], toolContext());

    expect($result['products'])->toHaveCount(1);
});

it('is registered with the assistant', function () {
    $registry = app(ToolRegistry::class);

    expect($registry->has('search_products'))->toBeTrue();

    $names = array_map(fn (array $tool) => $tool['function']['name'], $registry->schemas());

    expect($names)->toContain('search_products')
        ->toContain('search_knowledge_base')
        ->toContain('create_support_ticket')
        ->toContain('escalate_to_human');
});
