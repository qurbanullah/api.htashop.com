<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Session token lifetimes (seconds)
    |--------------------------------------------------------------------------
    |
    | Access tokens are deliberately short-lived: they travel on every request, so
    | a leak should be useless within the hour. The refresh token carries the
    | session instead — it is only ever sent to POST /api/v1/refresh, is rotated on
    | every use, and is stored hashed.
    |
    | `refresh_idle_ttl` closes a session that stops refreshing, so an abandoned
    | device does not keep a usable session for the full absolute lifetime.
    |
    */

    'access_ttl' => (int) env('AUTH_ACCESS_TTL', 60 * 60),

    'refresh_ttl' => (int) env('AUTH_REFRESH_TTL', 30 * 24 * 60 * 60),

    'refresh_idle_ttl' => (int) env('AUTH_REFRESH_IDLE_TTL', 14 * 24 * 60 * 60),

    /*
    |--------------------------------------------------------------------------
    | Cookies
    |--------------------------------------------------------------------------
    |
    | The web storefront keeps both tokens in httpOnly cookies so no script can
    | read them, and so `SameSite=Lax` keeps cross-site POSTs from carrying them
    | (same-site for htashop.com <-> api.htashop.com).
    |
    | The refresh cookie is path-scoped to the refresh endpoint: the long-lived
    | credential is then not attached to any other request. Native clients cannot
    | hold a cross-site cookie at all and send the token in the X-Refresh-Token
    | header instead (see frontend/src/lib/native-auth.ts).
    |
    */

    'cookie' => [
        'access' => 'hta_access_token',
        'refresh' => 'hta_refresh_token',
        'refresh_path' => '/api/v1/refresh',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Revoked and expired rows are kept this long for audit ("which device, when,
    | and why") before the pruning command removes them.
    |
    */

    'prune_after' => (int) env('AUTH_REFRESH_PRUNE_AFTER', 30 * 24 * 60 * 60),

];
