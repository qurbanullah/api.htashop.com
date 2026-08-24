<?php

namespace App\Http\Controllers\V1\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UnsubscribeController extends Controller
{
    /**
     * Show unsubscribe page
     */
    public function show(Request $request, string $token): View
    {
        $subscription = NewsletterSubscription::where('unsubscribe_token', $token)->first();

        if (!$subscription) {
            abort(404, 'Invalid unsubscribe link.');
        }

        $user = $subscription->subscribeable;

        return view('unsubscribe.show', compact('subscription', 'user', 'token'));
    }

    /**
     * Process unsubscribe request
     */
    public function unsubscribe(Request $request, string $token): RedirectResponse
    {
        $subscription = NewsletterSubscription::where('unsubscribe_token', $token)->first();

        if (!$subscription) {
            return redirect()->route('home')->with('error', 'Invalid unsubscribe link.');
        }

        $user = $subscription->subscribeable;
        $subscriptionType = $subscription->type;

        // Unsubscribe the user
        $subscription->unsubscribe();

        return redirect()->route('unsubscribe.success', $token)
            ->with('success', "You have been successfully unsubscribed from {$subscriptionType} notifications.");
    }

    /**
     * Show unsubscribe success page
     */
    public function success(Request $request, string $token): View
    {
        $subscription = NewsletterSubscription::where('unsubscribe_token', $token)->first();

        if (!$subscription) {
            abort(404, 'Invalid unsubscribe link.');
        }

        $user = $subscription->subscribeable;

        return view('unsubscribe.success', compact('subscription', 'user'));
    }

    /**
     * Resubscribe user
     */
    public function resubscribe(Request $request, string $token): RedirectResponse
    {
        $subscription = NewsletterSubscription::where('unsubscribe_token', $token)->first();

        if (!$subscription) {
            return redirect()->route('home')->with('error', 'Invalid link.');
        }

        $user = $subscription->subscribeable;
        $subscriptionType = $subscription->type;

        // Resubscribe the user
        $subscription->subscribe();

        return redirect()->route('unsubscribe.success', $token)
            ->with('success', "You have been successfully resubscribed to {$subscriptionType} notifications.");
    }
}
