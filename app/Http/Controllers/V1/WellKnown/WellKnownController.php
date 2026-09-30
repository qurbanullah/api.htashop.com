<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\WellKnown;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * App Links (Android) and Universal Links (iOS) association files.
 *
 * Both are proxied by the storefront's nginx from the site root, because the OS
 * only trusts them at `https://<storefront host>/.well-known/…`:
 *
 *   /.well-known/assetlinks.json
 *   /.well-known/apple-app-site-association
 *
 * The values come from `config/mobile_links.php` (env-driven), so completing
 * App Links is a matter of setting two environment variables rather than
 * hand-maintaining two JSON files. Anything still unconfigured answers 404: an
 * absent file makes verification fail cleanly, whereas a malformed one can be
 * cached by the platform and is harder to diagnose.
 */
class WellKnownController extends Controller
{
    private const CACHE_SECONDS = 3600;

    /**
     * Android App Links verification.
     *
     * @see https://developer.android.com/training/app-links/verify-android-applinks
     */
    public function assetLinks(): JsonResponse
    {
        $package = trim((string) config('mobile_links.android.package_name'));
        $fingerprints = $this->normaliseFingerprints(
            (array) config('mobile_links.android.sha256_cert_fingerprints', [])
        );

        if ($package === '' || $fingerprints === []) {
            return $this->notConfigured('assetlinks.json', [
                'package_name' => $package !== '',
                'sha256_cert_fingerprints' => $fingerprints !== [],
            ]);
        }

        return response()
            ->json([[
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $package,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ]])
            ->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }

    /**
     * iOS Universal Links association.
     *
     * Uses the modern `components` form, which is what iOS 13+ reads. Capacitor 7
     * supports iOS 14+ only, so the legacy `paths` form is not needed.
     *
     * @see https://developer.apple.com/documentation/xcode/supporting-associated-domains
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        $teamId = strtoupper(trim((string) config('mobile_links.ios.team_id')));
        $bundleId = trim((string) config('mobile_links.ios.bundle_id'));

        if (preg_match('/^[A-Z0-9]{10}$/', $teamId) !== 1 || $bundleId === '') {
            return $this->notConfigured('apple-app-site-association', [
                'team_id' => $teamId !== '',
                'bundle_id' => $bundleId !== '',
            ]);
        }

        return response()
            ->json([
                'applinks' => [
                    'details' => [[
                        'appIDs' => ["{$teamId}.{$bundleId}"],
                        // Each component is an object whose key is the path
                        // pattern Apple matches against — not a list of strings.
                        'components' => [
                            // iOS uses the FIRST component that matches a URL, so an
                            // exclusion has to be listed before anything that would
                            // match it: with the catch-all first, this entry would be
                            // unreachable and the association files themselves would
                            // open in the app instead of the browser.
                            [
                                '/' => '/.well-known/*',
                                'exclude' => true,
                            ],
                            // The storefront is a SPA, so every route it serves is
                            // openable in the app.
                            [
                                '/' => '/*',
                            ],
                        ],
                    ]],
                ],
            ])
            ->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }

    /**
     * Strip the colons, reject anything that is not 32 bytes of hex, and render
     * every fingerprint back in the canonical colon-separated form the platform
     * expects. A single hand-typed fingerprint that is malformed should not take
     * the whole file with it.
     *
     * @param  array<int, mixed>  $fingerprints
     * @return array<int, string>
     */
    private function normaliseFingerprints(array $fingerprints): array
    {
        $normalised = [];

        foreach ($fingerprints as $fingerprint) {
            $compact = strtoupper((string) preg_replace('/[^0-9a-fA-F]/', '', (string) $fingerprint));

            if (preg_match('/^[0-9A-F]{64}$/', $compact) !== 1) {
                Log::warning('Ignoring a malformed mobile_links SHA-256 fingerprint.', [
                    'length' => strlen($compact),
                ]);

                continue;
            }

            $normalised[] = implode(':', str_split($compact, 2));
        }

        return array_values(array_unique($normalised));
    }

    /**
     * @param  array<string, bool>  $configured
     */
    private function notConfigured(string $file, array $configured): JsonResponse
    {
        Log::info("Requested {$file} before it is fully configured.", $configured);

        return response()->json([
            'success' => false,
            'message' => 'Not configured.',
        ], 404);
    }
}
