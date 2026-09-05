<?php

namespace App\Http\Controllers\V1\Post;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use Illuminate\View\View;

/**
 * Web (non-API) controller that renders post "view in browser" pages
 * linked from post emails.
 */
class PostViewController extends Controller
{
    /**
     * Show a published post by slug (public view-online page).
     */
    public function show(string $slug): View
    {
        $post = Post::query()
            ->with(['creator', 'tags', 'categories', 'primaryCategory'])
            ->whereIn('status', ['published', 'sent'])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            abort(404);
        }

        return view('posts.show', compact('post'));
    }

    /**
     * Show a post via a token-gated link (used for posts that are not
     * published on the website).
     *
     * The token is a hash of the post uuid, the recipient's email address
     * and the application key — it proves the visitor was an email recipient.
     */
    public function view(string $uuid, string $token): View
    {
        $post = Post::query()
            ->with(['creator', 'tags', 'categories', 'primaryCategory'])
            ->where('uuid', $uuid)
            ->first();

        if (! $post) {
            abort(404);
        }

        if (! $this->tokenMatchesSubscriber($post, $token)) {
            abort(403, 'Invalid or expired link.');
        }

        return view('posts.show', compact('post'));
    }

    /**
     * Verify the given token against every active post subscriber.
     */
    private function tokenMatchesSubscriber(Post $post, string $token): bool
    {
        $subscribers = User::whereHas('subscriptions', function ($query) {
            $query->where('type', 'post')
                  ->where('is_subscribed', true);
        })->get(['email']);

        foreach ($subscribers as $user) {
            if (hash('sha256', $post->uuid . $user->email . config('app.key')) === $token) {
                return true;
            }
        }

        return false;
    }
}
