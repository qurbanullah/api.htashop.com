<?php

declare(strict_types=1);

namespace App\Support\Ai;

use Illuminate\Http\Request;

/**
 * Derives a stable, non-reversible identity for a chat visitor.
 *
 * The visitor holds an opaque random token; only a hash of that token is ever
 * persisted (as `visitor_key`). The raw token is never stored and the
 * conversation id is never sent to the client, so one visitor cannot read
 * another's transcript.
 *
 * Browsers get that token in an httpOnly cookie, which is same-site for the
 * storefront. Native shells cannot hold a cross-site cookie, so they send the
 * same value in the X-Chat-Token header instead — without it every device would
 * hash an empty token onto the same visitor key and share one transcript.
 *
 * A dedicated token is used instead of the Laravel session on purpose: chat is
 * guest-first and high-volume, and the API's session driver is the database.
 */
final class ChatIdentity
{
    public const COOKIE = 'chat_visitor';

    public const HEADER = 'X-Chat-Token';

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

        $header = self::headerToken($request);

        if ($header !== null) {
            return $header;
        }

        return self::cookieToken($request) ?? '';
    }

    /** The token a native client sent, or null when absent or malformed. */
    public static function headerToken(Request $request): ?string
    {
        return self::normalise($request->header(self::HEADER));
    }

    /** The token a browser holds in the httpOnly cookie, or null. */
    public static function cookieToken(Request $request): ?string
    {
        return self::normalise($request->cookie(self::COOKIE));
    }

    /** A visitor token is 32 random bytes rendered as lowercase hex. */
    public static function normalise(mixed $token): ?string
    {
        $token = trim((string) $token);

        return preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? $token : null;
    }
}
