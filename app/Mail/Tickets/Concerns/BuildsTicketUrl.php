<?php

declare(strict_types=1);

namespace App\Mail\Tickets\Concerns;

use App\Models\Ticket;

trait BuildsTicketUrl
{
    protected function buildTicketUrl(Ticket $ticket, string $recipientType = 'creator'): ?string
    {
        if ($recipientType === 'creator' && empty($ticket->user_id)) {
            return null;
        }

        $baseUrl = $recipientType === 'creator'
            ? config('app.manage_url')
            : config('app.admin_url');

        if (!$baseUrl) {
            return null;
        }

        if (!str_starts_with($baseUrl, 'http')) {
            $baseUrl = 'https://' . $baseUrl;
        }

        return rtrim($baseUrl, '/') . '/tickets/' . $ticket->uuid;
    }
}
