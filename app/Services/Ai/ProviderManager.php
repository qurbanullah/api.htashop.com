<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Interfaces\Ai\ChatProviderInterface;
use App\Interfaces\Ai\EmbeddingProviderInterface;
use App\Services\Ai\Providers\OpenAiCompatibleChatProvider;
use App\Services\Ai\Providers\OpenAiCompatibleEmbeddingProvider;
use InvalidArgumentException;

/**
 * Resolves the configured chat and embeddings providers.
 *
 * Providers are built from `config/ai.php`, so switching or adding a provider
 * is configuration, never a change to calling code.
 */
class ProviderManager
{
    /** @var array<string, ChatProviderInterface> */
    protected array $chat = [];

    protected ?EmbeddingProviderInterface $embeddings = null;

    /**
     * The provider used for replies.
     */
    public function chat(): ChatProviderInterface
    {
        $default = (string) config('ai.default', 'deepseek');
        $provider = $this->makeChat($default);

        if ($provider->isConfigured()) {
            return $provider;
        }

        // Fall back only when the fallback provider has its own credentials.
        $fallback = $this->fallbackChat();

        return $fallback ?? $provider;
    }

    /**
     * The configured fallback provider, when it has credentials.
     */
    public function fallbackChat(): ?ChatProviderInterface
    {
        $name = config('ai.fallback');

        if (empty($name)) {
            return null;
        }

        $provider = $this->makeChat((string) $name);

        return $provider->isConfigured() ? $provider : null;
    }

    public function makeChat(string $name): ChatProviderInterface
    {
        if (isset($this->chat[$name])) {
            return $this->chat[$name];
        }

        $config = config("ai.providers.{$name}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown chat provider [{$name}].");
        }

        // Every currently configured provider speaks the OpenAI wire format.
        return $this->chat[$name] = new OpenAiCompatibleChatProvider($name, $config);
    }

    public function embeddings(): EmbeddingProviderInterface
    {
        return $this->embeddings ??= new OpenAiCompatibleEmbeddingProvider(
            (array) config('ai.embeddings', [])
        );
    }
}
