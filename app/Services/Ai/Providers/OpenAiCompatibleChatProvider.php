<?php

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Interfaces\Ai\ChatProviderInterface;
use App\Support\Ai\ChatStreamChunk;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Chat provider for any OpenAI-compatible API (DeepSeek, OpenAI, a
 * self-hosted gateway, ...). Streams server-sent events so the visitor sees
 * tokens as they arrive, and assembles streamed tool-call fragments into
 * complete calls.
 */
class OpenAiCompatibleChatProvider implements ChatProviderInterface
{
    /**
     * @param  array<string, mixed>  $config  base_url, api_key, model, timeout
     */
    public function __construct(
        protected string $providerName,
        protected array $config,
    ) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['api_key']) && ! empty($this->config['model']);
    }

    public function stream(array $messages, array $tools = [], array $options = []): iterable
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                "Chat provider [{$this->providerName}] is not configured."
            );
        }

        $response = Http::withToken((string) $this->config['api_key'])
            ->timeout((int) ($this->config['timeout'] ?? 60))
            ->acceptJson()
            ->withOptions(['stream' => true])
            ->post($this->endpoint(), $this->payload($messages, $tools, $options));

        if ($response->status() >= 400) {
            throw new RuntimeException(
                "Chat provider [{$this->providerName}] returned HTTP {$response->status()}: "
                .mb_substr((string) $response->body(), 0, 500)
            );
        }

        yield from $this->parseStream($response->toPsrResponse()->getBody());
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function payload(array $messages, array $tools, array $options): array
    {
        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'stream' => true,
            // Ask for a final usage frame so token budgets can be enforced.
            'stream_options' => ['include_usage' => true],
            'temperature' => (float) ($options['temperature'] ?? 0.2),
            'max_tokens' => (int) ($options['max_tokens'] ?? 800),
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        return $payload;
    }

    /**
     * @return \Generator<int, ChatStreamChunk>
     */
    protected function parseStream(StreamInterface $stream): \Generator
    {
        $toolCalls = [];

        foreach ($this->readLines($stream) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, 5));

            if ($data === '[DONE]') {
                break;
            }

            $json = json_decode($data, true);

            if (! is_array($json)) {
                continue;
            }

            $choice = $json['choices'][0] ?? null;

            if (is_array($choice)) {
                $delta = $choice['delta'] ?? [];

                if (isset($delta['content']) && $delta['content'] !== '') {
                    yield ChatStreamChunk::delta((string) $delta['content']);
                }

                foreach ($delta['tool_calls'] ?? [] as $fragment) {
                    $index = (int) ($fragment['index'] ?? 0);
                    $toolCalls[$index] ??= ['id' => null, 'name' => null, 'arguments' => ''];

                    if (! empty($fragment['id'])) {
                        $toolCalls[$index]['id'] = (string) $fragment['id'];
                    }

                    if (! empty($fragment['function']['name'])) {
                        $toolCalls[$index]['name'] = (string) $fragment['function']['name'];
                    }

                    if (isset($fragment['function']['arguments'])) {
                        $toolCalls[$index]['arguments'] .= (string) $fragment['function']['arguments'];
                    }
                }

                if (! empty($choice['finish_reason'])) {
                    foreach ($toolCalls as $assembled) {
                        if (empty($assembled['name'])) {
                            continue;
                        }

                        yield ChatStreamChunk::toolCall([
                            'id' => $assembled['id'] ?? 'call_'.bin2hex(random_bytes(6)),
                            'name' => $assembled['name'],
                            'arguments' => $this->decodeArguments($assembled['arguments']),
                        ]);
                    }

                    $toolCalls = [];

                    yield ChatStreamChunk::finish((string) $choice['finish_reason']);
                }
            }

            if (! empty($json['usage']) && is_array($json['usage'])) {
                yield ChatStreamChunk::usage([
                    'prompt_tokens' => (int) ($json['usage']['prompt_tokens'] ?? 0),
                    'completion_tokens' => (int) ($json['usage']['completion_tokens'] ?? 0),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeArguments(string $arguments): array
    {
        if (trim($arguments) === '') {
            return [];
        }

        $decoded = json_decode($arguments, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Yield SSE lines from a PSR-7 stream, buffering partial reads.
     *
     * @return \Generator<int, string>
     */
    protected function readLines(StreamInterface $stream): \Generator
    {
        $buffer = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(8192);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($position = strpos($buffer, "\n")) !== false) {
                yield rtrim(substr($buffer, 0, $position), "\r");
                $buffer = substr($buffer, $position + 1);
            }
        }

        if (trim($buffer) !== '') {
            yield rtrim($buffer, "\r");
        }
    }

    protected function endpoint(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/').'/chat/completions';
    }
}
