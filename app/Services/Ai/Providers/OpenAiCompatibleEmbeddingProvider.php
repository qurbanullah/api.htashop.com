<?php

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Interfaces\Ai\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Embeddings for any OpenAI-compatible API. Point the base URL and model at
 * your chosen service — a hosted provider or a self-hosted multilingual model.
 */
class OpenAiCompatibleEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * @param  array<string, mixed>  $config  base_url, api_key, model, dimensions, timeout, batch_size
     */
    public function __construct(
        protected array $config,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && ! empty($this->config['api_key']);
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? '');
    }

    public function dimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 0);
    }

    public function embed(array $inputs): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Embeddings provider is not enabled.');
        }

        if ($inputs === []) {
            return [];
        }

        $batchSize = max(1, (int) ($this->config['batch_size'] ?? 64));
        $vectors = [];

        foreach (array_chunk($inputs, $batchSize) as $batch) {
            $response = Http::withToken((string) $this->config['api_key'])
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->acceptJson()
                ->post($this->endpoint(), [
                    'model' => $this->model(),
                    'input' => array_values($batch),
                ]);

            if ($response->failed()) {
                throw new RuntimeException(
                    "Embeddings provider returned HTTP {$response->status()}: "
                    .mb_substr((string) $response->body(), 0, 500)
                );
            }

            /** @var array<int, array<string, mixed>> $data */
            $data = $response->json('data', []);

            if (count($data) !== count($batch)) {
                throw new RuntimeException('Embeddings provider returned an unexpected number of vectors.');
            }

            // The API returns an `index` per item; sort so order is preserved.
            usort($data, fn (array $a, array $b) => ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0)));

            foreach ($data as $item) {
                /** @var array<int, float|int> $embedding */
                $embedding = $item['embedding'] ?? [];
                $vectors[] = array_map('floatval', $embedding);
            }
        }

        return $vectors;
    }

    protected function endpoint(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/').'/embeddings';
    }
}
