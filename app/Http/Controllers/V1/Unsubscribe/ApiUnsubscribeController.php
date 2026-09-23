<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Unsubscribe;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Subscribe;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * JSON unsubscribe API consumed by the storefront SPA at /unsubscribe/{token}.
 * The legacy HTML pages (UnsubscribeController) remain for post emails.
 */
class ApiUnsubscribeController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $subscription = $this->findByToken($token);

        if (!$subscription) {
            return ApiResponse::error('Invalid unsubscribe link.', null, 404);
        }

        return ApiResponse::success($this->payload($subscription));
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $subscription = $this->findByToken($token);

        if (!$subscription) {
            return ApiResponse::error('Invalid unsubscribe link.', null, 404);
        }

        $subscription->unsubscribe();

        return ApiResponse::success($this->payload($subscription), 'You have been unsubscribed.');
    }

    public function resubscribe(string $token): JsonResponse
    {
        $subscription = $this->findByToken($token);

        if (!$subscription) {
            return ApiResponse::error('Invalid unsubscribe link.', null, 404);
        }

        $subscription->subscribe();

        return ApiResponse::success($this->payload($subscription), 'You have been re-subscribed.');
    }

    private function findByToken(string $token): ?Subscribe
    {
        return Subscribe::query()->where('unsubscribe_token', $token)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Subscribe $subscription): array
    {
        $email = $subscription->email;
        $subscribable = $subscription->subscribable;

        if (!$email && $subscribable instanceof User) {
            $email = $subscribable->email;
        }

        return [
            'type' => $subscription->type,
            'type_label' => $this->typeLabel($subscription->type),
            'email' => $email,
            'is_subscribed' => $subscription->is_subscribed,
        ];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'newsletter' => 'newsletter updates',
            'post' => 'blog notifications',
            'product' => 'product notifications',
            'announcement' => 'announcements',
            default => $type . ' notifications',
        };
    }
}
