<?php

namespace App\Support\Punchout;

/**
 * BuyerCookie is opaque: the buyer generates it and expects the supplier to
 * echo it back in the PunchOutOrderMessage so the cart can be matched to the
 * shopper's session.
 *
 * Surrounding whitespace, however, is almost always transport noise rather
 * than part of the value: hand-written/sample cXML, pretty-printed documents,
 * HTML forms and copy/paste add regular spaces, non-breaking spaces and
 * zero-width characters around it. Buying systems compare the cookie they
 * generated against the one they receive, so we strip only the surrounding
 * noise and leave the value itself untouched.
 */
final class BuyerCookie
{
    private const EDGE_WHITESPACE = '/^[\s\p{Z}\x{FEFF}\x{200B}]+|[\s\p{Z}\x{FEFF}\x{200B}]+$/u';

    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace(self::EDGE_WHITESPACE, '', (string) $value);

        if ($normalized === null) {
            $normalized = trim((string) $value);
        }

        return $normalized === '' ? null : $normalized;
    }
}
