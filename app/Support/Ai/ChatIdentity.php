<?php

declare(strict_types=1);

namespace App\Support\Ai;

use Illuminate\Http\Request;

/**
 * Derives a stable, non-reversible identity for a chat visitor.
 *
 * The visitor's browser holds an opaque random token in an httpOnly cookie;
 * only a hash of that token is ever persisted (as `visitor_key`). The raw token
 * is never stored and the conversation id is never sent to the client, so one
 * visitor cannot read another's transcript.
 *
 * A dedicated cookie is used instead of the Laravel session on purpose: chat is
 * guest-first and high-volume, and the API's session driver is the database.
 */
final class ChatIdentity
{
    public const COOKIE = 'chat_visitor';

    public const ATTRIBUTE = 'chat_visitor_token';

    public static function visitorKey(Request $request): string
    {
        return hash('sha256', 'chat|'.self::token($request));
    }

    public static function token(Request $request): string
    {
        $token = (string) ($request->attributes->get(self::ATTRIBUTE) ?? '');

        if ($token !== '') {
            return $token;
        }

        return (string) $request->cookie(self::COOKIE, '');
    }
}
