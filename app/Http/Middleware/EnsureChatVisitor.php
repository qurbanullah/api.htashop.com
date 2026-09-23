<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Ai\ChatIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issues and resolves the visitor's chat token.
 *
 * A random opaque token is stored in an httpOnly cookie on first contact; the
 * application only ever persists a hash of it. Without this, an anonymous
 * visitor could not resume a thread, and the session store (the database here)
 * would take a write on every message.
 */
class EnsureChatVisitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $existing = (string) $request->cookie(ChatIdentity::COOKIE, '');
        $token = trim($existing);

        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
        }

        // Make the resolved token available to the rest of the request.
        $request->attributes->set(ChatIdentity::ATTRIBUTE, $token);

        $response = $next($request);

        if ($existing !== $token) {
            $response->headers->setCookie(new Cookie(
                name: ChatIdentity::COOKIE,
                value: $token,
                expire: now()->addYear()->getTimestamp(),
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }
}
