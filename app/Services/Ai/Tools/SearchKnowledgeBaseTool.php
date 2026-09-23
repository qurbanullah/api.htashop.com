<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Interfaces\Ai\ChatToolInterface;
use App\Interfaces\Ai\KnowledgeRetrieverInterface;
use App\Support\Ai\ChatToolContext;
use App\Support\Ai\RetrievedPassage;

/**
 * Lets the assistant look something up in the knowledge base mid-answer, for
 * questions that were not obvious from the initial retrieval.
 */
class SearchKnowledgeBaseTool implements ChatToolInterface
{
    public function __construct(
        protected KnowledgeRetrieverInterface $retriever,
    ) {}

    public function name(): string
    {
        return 'search_knowledge_base';
    }

    public function description(): string
    {
        return 'Search the approved HTAShop knowledge base for policies, shipping, '
            .'returns, and general account guidance.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The topic to look up, in the visitor\'s own words.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $arguments, ChatToolContext $context): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return ['passages' => []];
        }

        $passages = $this->retriever->retrieve(
            $query,
            $context->locale,
            (int) config('ai.retrieval.limit', 5)
        );

        return [
            'passages' => array_map(fn (RetrievedPassage $passage) => [
                'title' => $passage->title,
                'content' => $passage->content,
                'url' => $passage->sourceUrl,
            ], $passages),
        ];
    }
}
