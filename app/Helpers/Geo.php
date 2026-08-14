<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Geo
{
    /**
     * Get country info for an IP address.
     * Returns null on failure or an array: [country_name, country_code, flag]
     */
    public static function countryForIp(?string $ip)
    {
        if (empty($ip)) {
            if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: empty ip provided');
            return null;
        }

        // basic private/local checks
        if (in_array($ip, ['127.0.0.1', '::1']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            // if it's a private/reserved IP, return null
            if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: ip is private/reserved', ['ip' => $ip]);
            return null;
        }

        $cacheKey = 'geoip:' . $ip;

        // Check cache first so we can log hits on debug
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: cache hit', ['ip' => $ip, 'geo' => $cached]);
            return $cached;
        }

        // Try local MaxMind DB first (if installed and DB present)
        try {
            $mmdbPath = config('geo.mmdb_path') ?: storage_path('geo/GeoLite2-Country.mmdb');
            if (class_exists('\GeoIp2\Database\Reader') && file_exists($mmdbPath)) {
                try {
                    if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: querying MaxMind DB', ['path' => $mmdbPath, 'ip' => $ip]);
                    $reader = new \GeoIp2\Database\Reader($mmdbPath);
                    $rec = $reader->country($ip);
                    $code = $rec->country->isoCode ?? null;
                    $name = $rec->country->name ?? null;
                    if (! empty($code) || ! empty($name)) {
                        if (empty($name) && ! empty($code)) {
                            try { $name = \Symfony\Component\Intl\Countries::getName(strtoupper($code)); } catch (\Throwable $_) {}
                        }
                        $result = [
                            'country_name' => $name,
                            'country_code' => $code,
                            'flag' => $code ? self::flagFromCode($code) : null,
                        ];
                        if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: resolved from MaxMind', ['ip' => $ip, 'result' => $result]);
                        \Illuminate\Support\Facades\Cache::put($cacheKey, $result, 24 * 60 * 60);
                        return $result;
                    }
                } catch (\Throwable $e) {
                    if (config('app.debug')) \Illuminate\Support\Facades\Log::warning('geo: maxmind failed', ['ip' => $ip, 'error' => $e->getMessage()]);
                    // fallthrough to external providers
                }
            }
        } catch (\Throwable $_) {
            // ignore
        }

        try {
            if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: querying freegeoip.app', ['ip' => $ip]);

            $resp = \Illuminate\Support\Facades\Http::timeout(4)->get('https://freegeoip.app/json/' . $ip);
            if ($resp->ok()) {
                $data = $resp->json();
                $code = $data['country_code'] ?? null;
                $name = $data['country_name'] ?? null;

                if (! empty($name) || ! empty($code)) {
                    if (empty($name) && ! empty($code)) {
                        try {
                            $name = \Symfony\Component\Intl\Countries::getName(strtoupper($code));
                        } catch (\Throwable $_) {
                            // ignore if Intl not available
                        }
                    }

                    $result = [
                        'country_name' => $name,
                        'country_code' => $code,
                        'flag' => $code ? self::flagFromCode($code) : null,
                    ];

                    if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: resolved from freegeoip', ['ip' => $ip, 'result' => $result]);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $result, 24 * 60 * 60);
                    return $result;
                }

                if (config('app.debug')) \Illuminate\Support\Facades\Log::warning('geo: freegeoip returned empty country, falling back', ['ip' => $ip, 'body' => $resp->body()]);
            } else {
                if (config('app.debug')) \Illuminate\Support\Facades\Log::warning('geo: freegeoip returned non-ok', ['ip' => $ip, 'status' => $resp->status(), 'body' => $resp->body()]);
            }

            // Fallback: try ipapi.co
            if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: querying ipapi.co', ['ip' => $ip]);
            $r2 = \Illuminate\Support\Facades\Http::timeout(4)->get('https://ipapi.co/' . $ip . '/json/');
            if ($r2->ok()) {
                $d = $r2->json();
                $code = $d['country'] ?? ($d['country_code'] ?? null);
                $name = $d['country_name'] ?? null;
                if (empty($name) && ! empty($code)) {
                    try { $name = \Symfony\Component\Intl\Countries::getName(strtoupper($code)); } catch (\Throwable $_) {}
                }
                if (! empty($name) || ! empty($code)) {
                    $result = [
                        'country_name' => $name,
                        'country_code' => $code,
                        'flag' => $code ? self::flagFromCode($code) : null,
                    ];
                    if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: resolved from ipapi', ['ip' => $ip, 'result' => $result]);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $result, 24 * 60 * 60);
                    return $result;
                }
                if (config('app.debug')) \Illuminate\Support\Facades\Log::warning('geo: ipapi returned empty for ip', ['ip' => $ip, 'body' => $r2->body()]);
            } else {
                if (config('app.debug')) \Illuminate\Support\Facades\Log::warning('geo: ipapi returned non-ok', ['ip' => $ip, 'status' => $r2->status(), 'body' => $r2->body()]);
            }

            // Fallback: try ipwhois.app
            if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: querying ipwhois.app', ['ip' => $ip]);
            $r3 = \Illuminate\Support\Facades\Http::timeout(4)->get('https://ipwhois.app/json/' . $ip);
            if ($r3->ok()) {
                $d = $r3->json();
                $code = $d['country_code'] ?? ($d['country'] ?? null);
                $name = $d['country'] ?? ($d['country_name'] ?? null);
                if (empty($name) && ! empty($code)) {
                    try { $name = \Symfony\Component\Intl\Countries::getName(strtoupper($code)); } catch (\Throwable $_) {}
                }
                if (! empty($name) || ! empty($code)) {
                    $result = [
                        'country_name' => $name,
                        'country_code' => $code,
                        'flag' => $code ? self::flagFromCode($code) : null,
                    ];
                    if (config('app.debug')) \Illuminate\Support\Facades\Log::debug('geo: resolved from ipwhois', ['ip' => $ip, 'result' => $result]);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $result, 24 * 60 * 60);
                    return $result;
                }
                if (config('app.debug')) \Illuminate\Support\Facades\Log::warning('geo: ipwhois returned empty for ip', ['ip' => $ip, 'body' => $r3->body()]);
            } else {
                if (config('app.debug')) \Illuminate\Support\Facades\Log::warning('geo: ipwhois returned non-ok', ['ip' => $ip, 'status' => $r3->status(), 'body' => $r3->body()]);
            }

            // No provider succeeded; cache null to avoid repeated calls
            \Illuminate\Support\Facades\Cache::put($cacheKey, null, 24 * 60 * 60);
            return null;
        } catch (\Throwable $e) {
            if (config('app.debug')) \Illuminate\Support\Facades\Log::error('geo: exception', ['ip' => $ip, 'error' => $e->getMessage()]);
            \Illuminate\Support\Facades\Cache::put($cacheKey, null, 24 * 60 * 60);
            return null;
        }
    }

    protected static function flagFromCode(string $code): ?string
    {
        $code = strtoupper($code);
        if (strlen($code) !== 2) {
            return null;
        }

        $flag = '';
        // Regional indicator symbol offset
        $offset = 127397;
        foreach (str_split($code) as $char) {
            $flag .= mb_convert_encoding('&#' . ($offset + ord($char)) . ';', 'UTF-8', 'HTML-ENTITIES');
        }

        return $flag;
    }
}
