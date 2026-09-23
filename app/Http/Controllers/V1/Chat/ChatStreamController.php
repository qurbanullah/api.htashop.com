<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Chat\StoreChatMessageRequest;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Streams an assistant reply as server-sent events.
 *
 * POST (not EventSource) because the visitor's message travels in the body.
 * Frames are emitted by ChatService and only formatted here.
 */
class ChatStreamController extends Controller
{
    public function __construct(
        protected ConversationService $conversations,
        protected ChatService $chat,
    ) {}

    public function store(StoreChatMessageRequest $request): StreamedResponse
    {
        $conversation = $this->conversations->resolve(
            $request,
            $request->user(),
            $request->locale()
        );

        $message = $request->message();
        $user = $request->user();

        return response()->stream(function () use ($conversation, $message, $user): void {
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }

            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');

            try {
                foreach ($this->chat->stream($conversation, $message, $user) as $frame) {
                    // Stop generating (and spending tokens) if the visitor left.
                    if (connection_aborted()) {
                        break;
                    }

                    $this->emit($frame);
                }
            } catch (Throwable $exception) {
                logger()->error('Chat stream aborted', ['error' => $exception->getMessage()]);

                $this->emit(['event' => 'error', 'data' => [
                    'message' => 'The assistant is temporarily unavailable.',
                ]]);
            }

            $this->emit(['event' => 'end', 'data' => []]);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            // Tell nginx not to buffer the stream.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array{event: string, data: array<string, mixed>}  $frame
     */
    protected function emit(array $frame): void
    {
        echo 'event: '.$frame['event']."\n";
        echo 'data: '.json_encode($frame['data'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
