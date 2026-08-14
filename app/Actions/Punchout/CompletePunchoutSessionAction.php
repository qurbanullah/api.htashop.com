<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use App\Support\Punchout\Data\PunchoutCartData;

class CompletePunchoutSessionAction
{
    public function handle(PunchoutSession $session, PunchoutCartData $cartData): PunchoutSession
    {
        $session->status = 'completed';
        $session->cart_items = $cartData->items;
        $session->cart_payload = array_merge($cartData->payload, [
            'operation' => $cartData->operation,
            'return_url' => $cartData->returnUrl,
            'buyer_cookie' => $cartData->buyerCookie,
        ]);
        $session->completed_at = now();
        $session->cart_returned_at = now();
        $session->last_activity_at = now();
        $session->save();

        return $session->fresh();
    }
}
