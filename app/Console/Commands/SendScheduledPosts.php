<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Post\PostService;

class SendScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all post that are scheduled to be sent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $postService = app(PostService::class);
        $scheduledPost = $postService->getScheduledPost();

        if ($scheduledPost->isEmpty()) {
            $this->info('No post scheduled for sending.');
            return;
        }

        $this->info("Found {$scheduledPost->count()} post(s) ready to send.");

        foreach ($scheduledPost as $post) {
            try {
                $this->info("Sending post: {$post->title}");

                $result = $postService->sendPost($post);

                $this->info("✓ Post sent successfully!");
                $this->line("  - Sent to: {$result['sent_count']} recipients");
                $this->line("  - Failed: {$result['failed_count']} recipients");

            } catch (\Exception $e) {
                $this->error("✗ Failed to send post '{$post->title}': {$e->getMessage()}");
            }
        }

        $this->info('Scheduled post sending completed.');
    }
}
