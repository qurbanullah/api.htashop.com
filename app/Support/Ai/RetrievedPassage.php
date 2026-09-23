<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * A knowledge-base passage retrieved for grounding a reply.
 */
final class RetrievedPassage
{
    public function __construct(
        public readonly int $entryId,
        public readonly string $entryUuid,
        public readonly string $title,
        public readonly string $content,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $sourceType = null,
        public readonly float $score = 0.0,
        public readonly bool $restricted = false,
    ) {}

    /**
     * Shape used in the prompt and in the `citations` SSE frame.
     *
     * @return array<string, mixed>
     */
    public function toCitation(): array
    {
        return [
            'entry_id' => $this->entryId,
            'uuid' => $this->entryUuid,
            'title' => $this->title,
            'url' => $this->sourceUrl,
            'score' => round($this->score, 4),
        ];
    }
}
