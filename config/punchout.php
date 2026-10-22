<?php

return [
    'default_protocol' => env('PUNCHOUT_DEFAULT_PROTOCOL', 'cxml'),

    // Origin of the standalone punchout SPA. The CSP report collector accepts
    // reports only when their document-uri host matches this value.
    'app_url' => env('PUNCHOUT_APP_URL', 'https://punchout.htashop.com'),

    'session_ttl_minutes' => (int) env('PUNCHOUT_SESSION_TTL_MINUTES', 30),

    'session_idle_minutes' => (int) env('PUNCHOUT_SESSION_IDLE_MINUTES', 15),

    'session_absolute_minutes' => (int) env('PUNCHOUT_SESSION_ABSOLUTE_MINUTES', 120),

    'handoff_code_ttl_seconds' => (int) env('PUNCHOUT_HANDOFF_CODE_TTL_SECONDS', 120),

    'session_cleanup_retention_days' => (int) env('PUNCHOUT_SESSION_CLEANUP_RETENTION_DAYS', 7),

    'supported_protocols' => ['cxml', 'oci'],

    'max_line_items' => (int) env('PUNCHOUT_MAX_LINE_ITEMS', 200),

    // Inbound cXML OrderRequest. Off by default: an order is created only when
    // HTAShop is the seller of record. Tenants can override this in
    // settings.punchout.inbound_orders_enabled.
    'inbound_orders' => [
        'enabled' => (bool) env('PUNCHOUT_INBOUND_ORDERS_ENABLED', false),
    ],

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
