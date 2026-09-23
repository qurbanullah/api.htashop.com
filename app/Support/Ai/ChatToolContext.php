<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\Conversation;
use App\Models\User;

/**
 * Everything a tool needs to act on behalf of the current visitor.
 */
final class ChatToolContext
{
    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?User $user = null,
        public readonly string $locale = 'en',
    ) {}
}
