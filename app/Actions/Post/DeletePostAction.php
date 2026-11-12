<?php

namespace App\Actions\Post;

use App\Enums\PostStatusEnum;
use App\Models\Post;

class DeletePostAction
{
    public function execute(Post $post): bool
    {
        // Only allow deletion of drafts or unsent post
        // Compare with enum cases (status is cast to PostStatusEnum)
        if (! in_array($post->status, [PostStatusEnum::DRAFT, PostStatusEnum::SCHEDULED])) {
            throw new \Exception('Cannot delete sent or published post.');
        }

        return $post->delete();
    }
}
