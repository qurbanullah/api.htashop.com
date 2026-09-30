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
 * A random opaque token identifies the visitor: browsers get it in an httpOnly
 * cookie on first contact, native shells send it in the X-Chat-Token header.
 * The application only ever persists a hash of it. Without this, an anonymous
 * visitor could not resume a thread, and the session store (the database here)
 * would take a write on every message.
 */
class EnsureChatVisitor
{
    public function handle(Request $request, Closure $next): Response
    {
        // Native clients keep their own copy and never receive a cookie; the
        // browser path below is left exactly as it was.
        $headerToken = ChatIdentity::headerToken($request);

        if ($headerToken !== null) {
            $request->attributes->set(ChatIdentity::ATTRIBUTE, $headerToken);

            return $next($request);
        }

        $cookieToken = ChatIdentity::cookieToken($request);
        $token = $cookieToken ?? bin2hex(random_bytes(32));

        // Make the resolved token available to the rest of the request.
        $request->attributes->set(ChatIdentity::ATTRIBUTE, $token);

        $response = $next($request);

        if ($cookieToken === null) {
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
