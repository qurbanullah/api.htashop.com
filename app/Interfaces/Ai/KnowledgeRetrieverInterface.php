<?php

declare(strict_types=1);

namespace App\Interfaces\Ai;

use App\Support\Ai\RetrievedPassage;

/**
 * Retrieves grounding passages for a visitor question.
 */
interface KnowledgeRetrieverInterface
{
    /**
     * @return array<int, RetrievedPassage>
     */
    public function retrieve(string $query, ?string $locale = null, int $limit = 5): array;
}
