<?php

/*
|--------------------------------------------------------------------------
| Knowledge base
|--------------------------------------------------------------------------
|
| `knowledge_entries` is the source of truth for what the assistant may
| answer from. Entries are curated (see the admin portal) or ingested from
| existing content, then chunked and indexed into Typesense for retrieval.
|
| The `seed` list below bootstraps a fresh install. Every statement in it is
| traceable to a page we publish to customers — mainly the Shipping,
| Cancellations and Return/Refund Policy and the Terms of Use. It asserts no
| price, lead time or contractual term that those pages do not already state.
|
| Editors extend and correct these through the admin portal; re-running the
| seeder overwrites them by slug.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | How long entry bodies are split into retrievable passages.
    |
    */

    'chunk' => [
        'max_characters' => (int) env('KNOWLEDGE_CHUNK_CHARACTERS', 1200),
        'overlap_characters' => (int) env('KNOWLEDGE_CHUNK_OVERLAP', 150),
        // Skip passages shorter than this — they carry no retrievable meaning.
        'min_characters' => (int) env('KNOWLEDGE_CHUNK_MIN', 40),
    ],

    /*
    |--------------------------------------------------------------------------
    | Restricted topics
    |--------------------------------------------------------------------------
    |
    | The assistant must never answer these from model memory. It either links
    | the canonical source or escalates to a ticket. Matched case-insensitively
    | against the visitor message and used to bias the system prompt.
    |
    */

    'restricted_topics' => [
        'pricing',
        'quotation',
        'quote',
        'discount',
        'sla',
        'contract',
        'legal',
        'refund amount',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seed entries
    |--------------------------------------------------------------------------
    |
    | Sources:
    |   - Shipping, Cancellations and Return/Refund Policy: /policies/refund-policy
    |   - Terms of Use: /policies/terms-of-use
    |   - Checkout: /checkout
    |
    */

    'seed' => [
        [
            'title' => 'How to contact HTAShop support',
            'question' => 'How do I contact support? What are your contact details, phone number, email or address?',
            'body' => <<<'MD'
            You can reach our team in three ways:

            - Call our official landline: **042 35252579**
            - Email: **support@htashop.com**
            - Send a message from the **Contact** page at /contact

            We usually reply within 24 hours on business days (Mon–Sat). If you are signed in, mentioning your order number helps us answer faster.

            **Head office:** 263 Nishtar Block, Allam Iqbal Town, Lahore, 54570, Punjab, Pakistan.
            MD,
            'tags' => ['support', 'contact', 'contact details', 'phone', 'landline', 'number', 'email', 'address', 'postal address', 'call', 'location', 'located', 'office', 'head office', 'find us', 'reach us', 'where are you'],
            'source_url' => '/contact',
            'priority' => 5,
        ],
        [
            'title' => 'Tracking your order',
            'question' => 'How do I track my order?',
            'body' => 'Sign in and open **Account → Orders** (/account/orders) to see your orders. '
                .'Open an order to view its current status. '
                .'If the status has not changed and you need an update, contact support from /contact '
                .'and include the order number.',
            'tags' => ['orders', 'tracking', 'shipping'],
            'source_url' => '/account/orders',
        ],
        [
            'title' => 'Where to find our policies',
            'question' => 'Where can I read your policies?',
            'body' => "Our published policies are on the site:\n\n"
                ."- Refund policy: /policies/refund-policy\n"
                ."- Terms of use: /policies/terms-of-use\n"
                ."- Privacy policy: /policies/privacy-policy\n"
                ."- Cookies policy: /policies/cookies-policy\n\n"
                .'These pages are the authoritative source for shipping, returns and privacy terms.',
            'tags' => ['policy', 'refund', 'privacy', 'terms'],
            'source_url' => '/policies/refund-policy',
        ],
        [
            'title' => 'Sending feedback or reporting a problem',
            'question' => 'How do I send feedback or report a problem?',
            'body' => 'Use the **Feedback** page at /feedback to send general feedback. '
                .'For a problem with a specific order or product, contact support from /contact '
                .'and include as much detail as you can (order number, product, what happened).',
            'tags' => ['feedback', 'support'],
            'source_url' => '/feedback',
        ],

        /*
        |----------------------------------------------------------------------
        | Delivery
        |----------------------------------------------------------------------
        */

        [
            'title' => 'Delivery methods',
            'question' => 'What are the delivery methods?',
            'body' => <<<'MD'
            We deliver orders in two ways:

            - **Domestic (within Pakistan)** — using reputable courier companies and, where applicable, our own riders.
            - **International** — where we offer international delivery, using reputable international courier companies.

            Every order is delivered to the address you provide when placing the order. You can follow an order's progress under **Account → Orders** (/account/orders).

            The full policy is at /policies/refund-policy.
            MD,
            'tags' => ['delivery', 'shipping', 'courier', 'dispatch', 'delivery methods', 'domestic', 'international'],
            'source_url' => '/policies/refund-policy',
            'priority' => 5,
        ],
        [
            'title' => 'Delivery timeframes',
            'question' => 'How long does delivery take?',
            'body' => <<<'MD'
            - **Domestic (within Pakistan):** typically **3–7 business days from dispatch**, to the address you provided when ordering.
            - **International:** longer than domestic, and may be affected by customs and other circumstances outside our control.

            Delivery timelines are tentative only. We are not liable for delays arising from events outside our reasonable control, such as weather, courier or customs delays.

            The full policy is at /policies/refund-policy.
            MD,
            'tags' => ['delivery time', 'lead time', 'how long', 'shipping time', 'timeframe', 'business days'],
            'source_url' => '/policies/refund-policy',
            'priority' => 5,
        ],
        [
            'title' => 'International delivery, customs and duties',
            'question' => 'Do you deliver internationally and who pays customs or duties?',
            'body' => <<<'MD'
            Where we offer international delivery, we ship outside Pakistan using reputable international courier companies.

            **Customs, duties and taxes:** you may be required to pay customs duties, taxes or other charges when you receive goods shipped from Pakistan. These charges are your responsibility, and delays caused by customs are outside our control.

            The full policy is at /policies/refund-policy.
            MD,
            'tags' => ['international', 'abroad', 'overseas', 'customs', 'duties', 'taxes', 'import'],
            'source_url' => '/policies/refund-policy',
        ],

        /*
        |----------------------------------------------------------------------
        | Payment
        |----------------------------------------------------------------------
        */

        [
            'title' => 'Payment methods',
            'question' => 'What payment methods do you accept?',
            'body' => <<<'MD'
            - **Cash on Delivery (COD)** — pay in cash when your order is delivered.
            - **Online payment** — Safepay, JazzCash, EasyPaisa and UPaisa, offered where available for your order. Where online payment is not yet available, it is shown as coming soon at checkout (/checkout).

            Card payments are processed by PCI-DSS compliant payment partners; we never store your full card details. All payments must be authorised and cleared before an order is dispatched, and failed payments may result in the order being cancelled.

            See /policies/terms-of-use.
            MD,
            'tags' => ['payment', 'pay', 'cod', 'cash on delivery', 'jazzcash', 'easypaisa', 'upaisa', 'safepay', 'card'],
            'source_url' => '/policies/terms-of-use',
        ],

        /*
        |----------------------------------------------------------------------
        | Cancellations
        |----------------------------------------------------------------------
        */

        [
            'title' => 'Cancelling an order',
            'question' => 'Can I cancel my order?',
            'body' => <<<'MD'
            You can cancel an order **within 24 hours of placing it**. After that period, cancellation requests cannot be accepted, except where applicable consumer law requires otherwise.

            To cancel, use your order history under **Account → Orders** (/account/orders), or email support@htashop.com with your order number.

            See /policies/refund-policy.
            MD,
            'tags' => ['cancel', 'cancellation', 'change order', 'stop order', '24 hours'],
            'source_url' => '/policies/refund-policy',
            'priority' => 5,
        ],
        [
            'title' => 'Why an order may be declined or cancelled',
            'question' => 'Why was my order declined or cancelled?',
            'body' => <<<'MD'
            All orders are subject to availability and acceptance — a contract for the sale of goods is formed only when we confirm your order. We may decline to process an order when:

            - we no longer hold stock of the item,
            - we cannot ship to your location,
            - the item is no longer available,
            - there is a pricing error, suspected fraud, or the payment fails,
            - or for any other reason outside our control.

            If your order was declined and you need help, email support@htashop.com with the order number. See /policies/terms-of-use.
            MD,
            'tags' => ['declined', 'order cancelled', 'refused', 'out of stock', 'payment failed', 'order not processed'],
            'source_url' => '/policies/terms-of-use',
        ],

        /*
        |----------------------------------------------------------------------
        | Returns, exchanges and refunds
        |----------------------------------------------------------------------
        */

        [
            'title' => 'When you can return an item',
            'question' => 'Can I return an item?',
            'body' => <<<'MD'
            We operate a **no return, no exchange and no refund policy**, **except** where:

            - the goods you received are different from what you ordered (a wrong item, variant or quantity), or
            - the goods are defective or damaged.
            - Where applicable consumer law gives you a statutory right to withdraw from the purchase, we honour it.

            If your item is defective or incorrect, contact support@htashop.com first with complete evidence — receipts, pictures or videos — so we can approve the return quickly.

            See /policies/refund-policy.
            MD,
            'tags' => ['return', 'returns', 'refund', 'exchange', 'defective', 'damaged', 'wrong item', 'returns policy'],
            'source_url' => '/policies/refund-policy',
            'priority' => 5,
        ],
        [
            'title' => 'How to return an item',
            'question' => 'How do I return an item?',
            'body' => <<<'MD'
            Once a return has been approved:

            - We accept returns within **7 days of you receiving your order**.
            - Goods must be in a saleable condition — unused, with original packaging, manuals and accessories included.
            - Send the goods by courier to the address our support team provides once your return request is approved.
            - **You pay the shipping costs for returning the goods**, and they are non-refundable. If you receive a refund, the cost of return shipping is deducted from it.

            Start a return by emailing support@htashop.com. See /policies/refund-policy.
            MD,
            'tags' => ['return', 'send back', 'how to return', 'saleable', 'packaging', 'return shipping'],
            'source_url' => '/policies/refund-policy',
        ],
        [
            'title' => 'How long refunds take',
            'question' => 'How long does a refund take?',
            'body' => <<<'MD'
            Once we receive and inspect the returned goods and confirm they are in a saleable condition, we credit the refund within **7–14 business days of receiving the goods**.

            - **Online payments (Safepay, JazzCash, EasyPaisa, UPaisa):** refunded to the original payment method. Your bank or payment provider may take additional time to post the credit.
            - **Cash on Delivery (COD):** refunded by bank transfer to the account details you provide.
            - Return shipping costs are deducted from the refund.

            If a refund has not arrived within that timeframe, check with your bank or payment provider first, then email support@htashop.com with your order number.

            See /policies/refund-policy.
            MD,
            'tags' => ['refund', 'money back', 'how long', 'refund time', 'reimbursement', '7-14 business days'],
            'source_url' => '/policies/refund-policy',
            'priority' => 5,
        ],
        [
            'title' => 'Exchanges and store credit',
            'question' => 'Can I exchange an item or get store credit?',
            'body' => <<<'MD'
            Instead of a refund, we may provide **store credit** so that you can exchange the returned goods for another item of the same value from our website.

            Store credit is issued to your HTAShop account and can be applied at checkout.

            See /policies/refund-policy.
            MD,
            'tags' => ['exchange', 'store credit', 'swap', 'credit', 'replace'],
            'source_url' => '/policies/refund-policy',
        ],

        /*
        |----------------------------------------------------------------------
        | Support and company
        |----------------------------------------------------------------------
        */

        [
            'title' => 'Making a complaint about a product or order',
            'question' => 'How do I make a complaint?',
            'body' => <<<'MD'
            For any complaint or query about our website, products or service, call our official landline on **042 35252579**, email **support@htashop.com**, or use the **Contact** page at /contact.

            We use our best endeavours to respond within **2 business days**.

            For a complaint about a defective or incorrect product, please share complete evidence — receipts, pictures and videos — so we can resolve the issue quickly.

            See /policies/refund-policy.
            MD,
            'tags' => ['complaint', 'complain', 'problem', 'issue', 'defect', 'damaged', 'report a problem'],
            'source_url' => '/policies/refund-policy',
            'priority' => 5,
        ],
        [
            'title' => 'Who operates HTAShop',
            'question' => 'Who runs HTAShop and where are you based?',
            'body' => <<<'MD'
            HTAShop is owned and operated by **High Tech Advancement Solutions (Private) Limited**, CUIN 0321375, with its principal place of business at **263 Nishtar Block, Allam Iqbal Town, Lahore, 54570, Punjab, Pakistan**.

            You can call our official landline on **042 35252579** or email **support@htashop.com**.

            Our terms are governed by the laws of the Islamic Republic of Pakistan. Product names, trademarks and logos of third parties remain the property of their respective owners.

            See /policies/terms-of-use.
            MD,
            'tags' => ['company', 'who owns', 'about', 'based', 'pakistan', 'legal entity', 'address', 'phone', 'located'],
            'source_url' => '/policies/terms-of-use',
        ],
    ],

];
