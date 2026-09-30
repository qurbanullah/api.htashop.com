<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile app links (App Links / Universal Links)
    |--------------------------------------------------------------------------
    |
    | Tells iOS and Android which app to open for a https://htashop.com link.
    | Both files are served from the site root by WellKnownController and proxied
    | by the storefront's nginx:
    |
    |   https://htashop.com/.well-known/assetlinks.json            (Android)
    |   https://htashop.com/.well-known/apple-app-site-association (iOS)
    |
    | The in-app routing is already implemented (src/lib/deep-link.ts); only these
    | two files make the OS hand the link to the app. Until the values below are
    | set, both endpoints return 404 rather than an unverifiable file.
    |
    | Android: the SHA-256 fingerprints of the signing certificate. Get them with
    |   keytool -list -v -keystore <your-keystore> -alias <your-alias>
    | and include the release *and* (optionally) the Play App Signing certificate.
    | Comma-separate several.
    |
    | iOS: the Apple Developer Team ID plus the bundle identifier, and the same
    | bundle id must be added to the `applinks:` Associated Domains entitlement.
    |
    | The hosts the apps claim are deliberately NOT listed here: these files are
    | host-agnostic (each platform fetches them from the host it is verifying), so a
    | list in this file would be documentation that silently drifts. The host list
    | lives where it has an effect:
    |
    |   Android — the App Links intent-filter in android/app/src/main/AndroidManifest.xml
    |   iOS     — the `applinks:` entries in the iOS Associated Domains capability
    |   In-app  — WEB_HOSTS in frontend/src/lib/deep-link.ts
    |
    */

    'android' => [
        'package_name' => (string) env(
            'MOBILE_ANDROID_PACKAGE',
            'com.htasol.htashop'
        ),
        'sha256_cert_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MOBILE_ANDROID_SHA256', ''))
        ))),
    ],

    'ios' => [
        'team_id' => (string) env('MOBILE_IOS_TEAM_ID', ''),
        'bundle_id' => (string) env('MOBILE_IOS_BUNDLE_ID', 'com.htasol.htashop'),
    ],
];
