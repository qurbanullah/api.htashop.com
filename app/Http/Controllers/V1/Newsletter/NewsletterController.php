<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Newsletter;

use App\Actions\Subscription\SubscribeNewsletterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Newsletter\SubscribeNewsletterRequest;
use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function subscribe(
        SubscribeNewsletterRequest $request,
        SubscribeNewsletterAction $action
    ): JsonResponse {
        $tenantId = TenantContext::publicTenantId($request);

        [$subscription, $status] = $action->handle(
            $request->string('email')->trim()->toString(),
            $tenantId,
            array_filter([
                'source' => 'newsletter',
                'source_page' => $request->string('source_page')->trim()->toString() ?: null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'consent' => $request->boolean('consent'),
            ], static fn ($value) => $value !== null && $value !== ''),
        );

        Log::info('Newsletter subscribe request handled', [
            'email' => $subscription->email,
            'status' => $status,
            'tenant_id' => $tenantId,
        ]);

        if ($status === 'subscribed' && config('mail.newsletter_welcome_enabled')) {
            try {
                Mail::to($subscription->email)->queue(new NewsletterWelcomeMail($subscription));
            } catch (\Throwable $throwable) {
                Log::warning('Failed to queue newsletter welcome email', [
                    'email' => $subscription->email,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        $message = match ($status) {
            'already_subscribed' => 'You are already subscribed to our newsletter. Thank you!',
            'resubscribed' => 'Welcome back! You have been re-subscribed to our newsletter.',
            default => 'Thanks for subscribing! We will send updates straight to your inbox.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'email' => $subscription->email,
                'is_subscribed' => $subscription->is_subscribed,
            ],
        ], $status === 'subscribed' ? 201 : 200);
    }
}
