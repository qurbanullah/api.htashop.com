<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Console\Command;

/**
 * Sends a notification to a user's registered devices.
 *
 * The way to prove push works end to end before any business event is wired to it:
 * open the app on a device, sign in, then:
 *
 *   php artisan push:test you@example.com --title=HTAShop --body="Push works"
 *   php artisan push:test 12 --link=/account/orders
 *
 * The counts it prints are the whole story: `skipped` means push is off or the
 * transport has no credentials, `failed` means the provider refused it, `pruned`
 * means a token was dead and has been forgotten.
 */
class SendTestPushCommand extends Command
{
    protected $signature = 'push:test
        {user : A user id or an email address}
        {--title=HTAShop : Notification title}
        {--body=Test notification : Notification body}
        {--link= : In-app path the tap should open, e.g. /account/orders}';

    protected $description = 'Send a test push notification to a user\'s devices';

    public function handle(PushService $pushService): int
    {
        $user = $this->findUser((string) $this->argument('user'));

        if ($user === null) {
            $this->error('No such user. Pass an id or an email address.');

            return self::FAILURE;
        }

        $devices = $user->deviceTokens()->count();

        if ($devices === 0) {
            $this->error("{$user->email} has no registered devices. Sign in on the app first — the token is registered on sign-in.");

            return self::FAILURE;
        }

        $link = (string) $this->option('link');

        $counts = $pushService->sendToUser(
            $user,
            (string) $this->option('title'),
            (string) $this->option('body'),
            $link !== '' ? ['link' => $link] : [],
        );

        $this->table(['delivered', 'failed', 'skipped', 'pruned'], [[
            $counts['delivered'],
            $counts['failed'],
            $counts['skipped'],
            $counts['pruned'],
        ]]);

        if ($counts['delivered'] > 0) {
            return self::SUCCESS;
        }

        if ($counts['skipped'] > 0) {
            $this->warn('Nothing was sent: push is disabled (PUSH_ENABLED) or the credentials for that platform are missing. iOS waits on the Apple account.');

            return self::FAILURE;
        }

        $this->warn('Nothing was delivered. Check the log for the provider\'s answer.');

        return self::FAILURE;
    }

    private function findUser(string $identifier): ?User
    {
        if (ctype_digit($identifier)) {
            return User::query()->find((int) $identifier);
        }

        return User::query()->where('email', $identifier)->first();
    }
}
