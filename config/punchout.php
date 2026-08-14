<?php

return [
    'default_protocol' => env('PUNCHOUT_DEFAULT_PROTOCOL', 'cxml'),

    'session_ttl_minutes' => (int) env('PUNCHOUT_SESSION_TTL_MINUTES', 30),

    'session_cleanup_retention_days' => (int) env('PUNCHOUT_SESSION_CLEANUP_RETENTION_DAYS', 7),

    'supported_protocols' => ['cxml', 'oci'],

    'protocols' => [
        'cxml' => [
            'content_type' => 'text/xml',
            'setup_operation' => 'PunchOutSetupRequest',
            'cart_operation' => 'PunchOutOrderMessage',
            'mode' => 'request',
            'catalog_url' => env('PUNCHOUT_CXML_CATALOG_URL'),
            'credentials' => [
                'sender_identity' => env('PUNCHOUT_CXML_SENDER_IDENTITY'),
                'shared_secret' => env('PUNCHOUT_CXML_SHARED_SECRET'),
                'from_identity' => env('PUNCHOUT_CXML_FROM_IDENTITY'),
                'to_identity' => env('PUNCHOUT_CXML_TO_IDENTITY'),
            ],
        ],
        'oci' => [
            'content_type' => 'text/html; charset=UTF-8',
            'setup_operation' => 'OCI_LOGIN',
            'cart_operation' => 'BACKGROUND_POST',
            'mode' => 'form',
            'catalog_url' => env('PUNCHOUT_OCI_CATALOG_URL'),
            'credentials' => [
                'username' => env('PUNCHOUT_OCI_USERNAME'),
                'password' => env('PUNCHOUT_OCI_PASSWORD'),
            ],
        ],
    ],
];
