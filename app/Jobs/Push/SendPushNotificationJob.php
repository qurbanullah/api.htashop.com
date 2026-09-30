<?php

namespace App\Jobs\Push;

use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers a notification to every device a user has registered.
 *
 * Queued because delivery talks to Google — and, later, Apple — so whatever
 * triggers a notification must not wait on someone else's API.
 *
 * Deliberately not retried. PushService absorbs delivery failures and reports them
 * in its counts, so a retry could only fire after an infrastructure error part-way
 * through the fan-out, and re-sending would notify the devices that already got it.
 * A duplicate push is more annoying to a user than a missed one.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * @param  int  $userId  stored as an id, not a model: a user deleted between
     *                       queueing and delivery should drop the notification
     * @param  array<string, mixed>  $data  extra payload keys, e.g. a deep link
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(PushService $pushService): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $counts = $pushService->sendToUser($user, $this->title, $this->body, $this->data);

        Log::info('Push notification dispatched.', [
            'user_id' => $this->userId,
            'title' => $this->title,
            ...$counts,
        ]);
    }
}
