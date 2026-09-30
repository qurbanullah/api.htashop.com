<?php

/*
|--------------------------------------------------------------------------
| Push notifications
|--------------------------------------------------------------------------
|
| Delivery to the native shells (frontend/CAPACITOR.md). Android goes through
| Firebase Cloud Messaging and iOS straight to APNs: two transports, two sets of
| credentials, no shared account.
|
| Nothing here is needed to run the API. With no credentials the send path logs
| and reports "skipped" rather than failing, so the app builds and runs without
| push at all.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    */

    // Off: sends are skipped and logged. Tokens stay registered, so switching it
    // back on resumes delivery without anyone re-opening the app.
    'enabled' => (bool) env('PUSH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Android — Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | The service-account JSON from Firebase → Project settings → Service accounts.
    | api/storage/keys is gitignored and mounted on the host, which is where the
    | Passport keys live too.
    |
    */

    'fcm' => [
        'credentials' => env('FCM_SERVICE_ACCOUNT_PATH', storage_path('keys/firebase-service-account.json')),

        // Left null, the project id is read from the credentials file.
        'project_id' => env('FCM_PROJECT_ID'),

        // Seconds. A send is a background job, so this is a ceiling, not a budget.
        'timeout' => (int) env('FCM_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | iOS — APNs
    |--------------------------------------------------------------------------
    |
    | The .p8 from the Apple Developer portal → Keys, plus its Key ID. Until those
    | exist, iOS sends are reported as skipped: there is no Apple account yet, so
    | there is nothing to send to. The Team ID is the same one App Links uses.
    |
    */

    'apns' => [
        'key_path' => env('APNS_KEY_PATH', storage_path('keys/apns-auth-key.p8')),
        'key_id' => (string) env('APNS_KEY_ID', ''),
        'team_id' => (string) env('MOBILE_IOS_TEAM_ID', ''),
        'bundle_id' => (string) env('MOBILE_IOS_BUNDLE_ID', 'com.htasol.htashop'),

        // A debug build registers with the sandbox APNs environment; a TestFlight
        // or App Store build registers with production. Tokens are not
        // interchangeable between the two.
        'sandbox' => (bool) env('APNS_SANDBOX', false),

        'timeout' => (int) env('APNS_TIMEOUT', 15),
    ],

];
