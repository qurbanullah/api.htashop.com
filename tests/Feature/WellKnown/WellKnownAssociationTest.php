<?php

/**
 * App Links / Universal Links association files.
 *
 * The platform only trusts these at the storefront root, and a malformed file is
 * worse than a missing one (it can be cached and is hard to diagnose), so an
 * unconfigured deployment must answer 404 rather than emit a partial file.
 */
const ANDROID_FINGERPRINT = 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99';

function configureMobileLinks(): void
{
    config([
        'mobile_links.android.package_name' => 'com.htasol.htashop',
        'mobile_links.android.sha256_cert_fingerprints' => [ANDROID_FINGERPRINT],
        'mobile_links.ios.team_id' => 'A1B2C3D4E5',
        'mobile_links.ios.bundle_id' => 'com.htasol.htashop',
    ]);
}

describe('assetlinks.json', function () {
    it('serves the Android association file when configured', function () {
        configureMobileLinks();

        $this->getJson('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls')
            ->assertJsonPath('0.target.namespace', 'android_app')
            ->assertJsonPath('0.target.package_name', 'com.htasol.htashop')
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', ANDROID_FINGERPRINT);
    });

    it('is served as JSON with no redirect', function () {
        configureMobileLinks();

        $response = $this->get('/.well-known/assetlinks.json');

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/json');
    });

    it('404s while the fingerprints are unset', function () {
        config(['mobile_links.android.sha256_cert_fingerprints' => []]);

        $this->getJson('/.well-known/assetlinks.json')->assertNotFound();
    });

    it('normalises a fingerprint typed without colons', function () {
        configureMobileLinks();
        config([
            'mobile_links.android.sha256_cert_fingerprints' => [
                str_replace(':', '', ANDROID_FINGERPRINT),
            ],
        ]);

        $this->getJson('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', ANDROID_FINGERPRINT);
    });

    it('drops a malformed fingerprint without dropping the file', function () {
        configureMobileLinks();
        config([
            'mobile_links.android.sha256_cert_fingerprints' => ['too-short', ANDROID_FINGERPRINT],
        ]);

        $this->getJson('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonCount(1, '0.target.sha256_cert_fingerprints');
    });

    it('404s when every fingerprint is malformed', function () {
        configureMobileLinks();
        config(['mobile_links.android.sha256_cert_fingerprints' => ['nonsense']]);

        $this->getJson('/.well-known/assetlinks.json')->assertNotFound();
    });

    it('de-duplicates repeated fingerprints', function () {
        configureMobileLinks();
        config([
            'mobile_links.android.sha256_cert_fingerprints' => [
                ANDROID_FINGERPRINT,
                str_replace(':', '', ANDROID_FINGERPRINT),
            ],
        ]);

        $this->getJson('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonCount(1, '0.target.sha256_cert_fingerprints');
    });
});

describe('apple-app-site-association', function () {
    it('serves the iOS association file in the shape Apple expects', function () {
        configureMobileLinks();

        $response = $this->getJson('/.well-known/apple-app-site-association');

        $response->assertOk()
            ->assertJsonPath('applinks.details.0.appIDs.0', 'A1B2C3D4E5.com.htasol.htashop');

        // Each component must be an object keyed by the path pattern; a list of
        // bare strings would be silently ignored by iOS.
        expect($response->json('applinks.details.0.components'))->toBe([
            ['/' => '/.well-known/*', 'exclude' => true],
            ['/' => '/*'],
        ]);
    });

    it('lists the exclusion before the catch-all that would swallow it', function () {
        configureMobileLinks();

        $components = $this->getJson('/.well-known/apple-app-site-association')
            ->assertOk()
            ->json('applinks.details.0.components');

        $excludeAt = array_search('/.well-known/*', array_column($components, '/'), true);
        $catchAllAt = array_search('/*', array_column($components, '/'), true);

        // iOS takes the first component that matches, so listing the catch-all
        // first would make the exclusion unreachable.
        expect($excludeAt)->toBeInt()
            ->and($catchAllAt)->toBeInt()
            ->and($excludeAt)->toBeLessThan($catchAllAt);
    });

    it('uses the extension-less path with a JSON content type', function () {
        configureMobileLinks();

        $response = $this->get('/.well-known/apple-app-site-association');

        $response->assertOk();
        // Apple rejects a redirect and requires the JSON content type.
        expect($response->headers->get('content-type'))->toContain('application/json')
            ->and($response->headers->get('location'))->toBeNull();
    });

    it('excludes the association files themselves', function () {
        configureMobileLinks();

        $components = $this->getJson('/.well-known/apple-app-site-association')
            ->assertOk()
            ->json('applinks.details.0.components');

        expect(collect($components)->contains(
            fn (array $component): bool => ($component['/'] ?? null) === '/.well-known/*'
                && ($component['exclude'] ?? null) === true
        ))->toBeTrue();
    });

    it('404s while the team id is unset', function () {
        config(['mobile_links.ios.team_id' => '']);

        $this->getJson('/.well-known/apple-app-site-association')->assertNotFound();
    });

    it('404s on a team id that is not a real shape', function () {
        config(['mobile_links.ios.team_id' => 'not-a-team-id']);

        $this->getJson('/.well-known/apple-app-site-association')->assertNotFound();
    });
});

describe('mobile_links configuration', function () {
    it('keeps the host list out of config, where it would do nothing', function () {
        // The hosts an app claims live in the Android App Links intent-filter and
        // the iOS Associated Domains entitlement — not here. The association files
        // themselves are host-agnostic, so a `hosts` key would only mislead.
        expect(config('mobile_links.hosts'))->toBeNull();
    });
});
