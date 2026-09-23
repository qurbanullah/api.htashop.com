<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Interfaces\Ai\ChatToolInterface;
use App\Support\Ai\ChatToolContext;
use Throwable;

/**
 * The set of tools the assistant may call. Only registered tools are ever
 * exposed to the model, and an unknown name is refused — the allowlist is the
 * boundary between "the model asked" and "the application did".
 */
class ToolRegistry
{
    /** @var array<string, ChatToolInterface> */
    protected array $tools = [];

    /**
     * @param  iterable<ChatToolInterface>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(ChatToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function schemas(): array
    {
        return array_values(array_map(
            fn (ChatToolInterface $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parameters(),
                ],
            ],
            $this->tools
        ));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments, ChatToolContext $context): array
    {
        if (! $this->has($name)) {
            return ['error' => "Unknown tool [{$name}]."];
        }

        try {
            return $this->tools[$name]->handle($arguments, $context);
        } catch (Throwable $exception) {
            logger()->warning('Chat tool failed', [
                'tool' => $name,
                'error' => $exception->getMessage(),
            ]);

            return ['error' => 'The requested action could not be completed.'];
        }
    }
}
