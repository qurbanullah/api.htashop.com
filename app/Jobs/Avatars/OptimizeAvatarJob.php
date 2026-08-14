<?php

namespace App\Jobs\Avatars;

use App\Models\User;
use App\Services\Avatars\AvatarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OptimizeAvatarJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $avatarFilename,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AvatarService $avatarService): void
    {
        try {
            $user = User::with('avatars')->find($this->userId);
            if (!$user) {
                Log::warning('User not found for avatar optimization', [
                    'user_id' => $this->userId,
                ]);
                return;
            }

            Log::info('Starting avatar optimization job', [
                'user_id' => $this->userId,
                'avatar_filename' => $this->avatarFilename,
            ]);

            // Run optimization (generate additional formats, re-compress, etc.)
            $result = $avatarService->optimizeAvatarIfNeeded($user);

            if ($result) {
                Log::info('Avatar optimization completed successfully', [
                    'user_id' => $this->userId,
                    'avatar_filename' => $this->avatarFilename,
                ]);
            } else {
                Log::warning('Avatar optimization did not produce expected results', [
                    'user_id' => $this->userId,
                    'avatar_filename' => $this->avatarFilename,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Avatar optimization job failed', [
                'user_id' => $this->userId,
                'avatar_filename' => $this->avatarFilename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Retry up to 3 times with exponential backoff
            if ($this->attempts() < 3) {
                $this->release(60 * $this->attempts());
            }
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Avatar optimization job failed permanently after all retries', [
            'user_id' => $this->userId,
            'avatar_filename' => $this->avatarFilename,
            'error' => $exception->getMessage(),
        ]);
    }
}
