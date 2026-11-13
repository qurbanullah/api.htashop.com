<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expiry lifecycle
    |--------------------------------------------------------------------------
    |
    | `reminder_days` is how long before a revision's validity ends the buyer
    | gets a "your quote is about to expire" email. The quotes:expire command
    | runs hourly and processes both reminders and hard expiry.
    |
    */
    'expiry' => [
        'reminder_days' => (int) env('QUOTE_EXPIRY_REMINDER_DAYS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Buyer portal links
    |--------------------------------------------------------------------------
    |
    | Emails link to a signed storefront page. The signature lifetime is the
    | revision's validity plus a grace window so a buyer can still open the
    | offer shortly after it lapses. PDF links are short-lived on purpose
    | because the portal mints a fresh one each time it loads.
    |
    */
    'portal' => [
        'link_grace_days' => (int) env('QUOTE_PORTAL_LINK_GRACE_DAYS', 14),
        'pdf_minutes' => (int) env('QUOTE_PORTAL_PDF_MINUTES', 30),
        // Accept/decline links are minted when the portal loads. A full day
        // covers a buyer who leaves the page open over lunch or overnight.
        'action_minutes' => (int) env('QUOTE_PORTAL_ACTION_MINUTES', 1440),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotation PDF
    |--------------------------------------------------------------------------
    |
    | Attaching the PDF to the outbound email adds a synchronous Gotenberg
    | round-trip to the send. It is disabled in tests so the suite stays
    | deterministic, and can be switched off per environment if the PDF
    | service is unavailable.
    |
    */
    'pdf' => [
        'attach_to_email' => (bool) env(
            'QUOTE_PDF_ATTACH_EMAIL',
            env('APP_ENV', 'production') !== 'testing',
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal notifications
    |--------------------------------------------------------------------------
    |
    | When a buyer accepts or declines, the assigned owner is emailed. If the
    | quote has no owner the fallback mailbox receives the notification.
    |
    */
    'notifications' => [
        'fallback_email' => env(
            'QUOTE_NOTIFICATION_EMAIL',
            env('MAIL_ADMIN_EMAIL', 'support@htashop.com'),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Weekly performance digest
    |--------------------------------------------------------------------------
    |
    | Recipients are the configured list plus every admin/super-admin. When the
    | list is empty the fallback mailbox above receives it instead.
    |
    */
    'digest' => [
        'days' => (int) env('QUOTE_DIGEST_DAYS', 30),
        'recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('QUOTE_DIGEST_RECIPIENTS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document header
    |--------------------------------------------------------------------------
    |
    | Printed on the quotation PDF. Mirrors the storefront's COMPANY constant.
    |
    */
    'company' => [
        'name' => env('QUOTE_COMPANY_NAME', 'High Tech Advancement Solutions (Private) Limited'),
        'address' => env(
            'QUOTE_COMPANY_ADDRESS',
            '263 Nishtar Block, Allama Iqbal Town, Lahore, 54570, Punjab, Pakistan',
        ),
        'phone' => env('QUOTE_COMPANY_PHONE', '042 35252579'),
        'email' => env('QUOTE_COMPANY_EMAIL', 'sales@htashop.com'),
        'website' => env('QUOTE_COMPANY_WEBSITE', 'https://htashop.com'),
    ],

];
