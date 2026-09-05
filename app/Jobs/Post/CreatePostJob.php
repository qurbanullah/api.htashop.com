<?php

namespace App\Jobs\Post;

use App\Services\Post\PostService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreatePostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $postData;
    protected int $createdByUserId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $postData, int $createdByUserId)
    {
        $this->postData = $postData;
        $this->createdByUserId = $createdByUserId;
    }

    /**
     * Execute the job.
     */
    public function handle(PostService $postService): void
    {
        // Pass the user ID explicitly to the service since auth() is not available in queue context
        $postService->createPost($this->postData, $this->createdByUserId);
    }
}
