<?php

declare(strict_types=1);

namespace App\Interfaces\Ai;

/**
 * An embeddings provider used for hybrid (keyword + vector) retrieval.
 *
 * DeepSeek exposes no embeddings endpoint, so this is a separate service. Any
 * OpenAI-compatible embeddings API works. For a multilingual corpus (en/de/ur)
 * prefer a multilingual model over an English-only one.
 */
interface EmbeddingProviderInterface
{
    /**
     * Whether embeddings are enabled and credentials are present.
     */
    public function isEnabled(): bool;

    /**
     * Model identifier, e.g. `text-embedding-3-small`.
     */
    public function model(): string;

    /**
     * Vector size. Must match the Typesense field `num_dim`.
     */
    public function dimensions(): int;

    /**
     * Embed a batch of texts, preserving input order.
     *
     * @param  array<int, string>  $inputs
     * @return array<int, array<int, float>>
     */
    public function embed(array $inputs): array;
}
