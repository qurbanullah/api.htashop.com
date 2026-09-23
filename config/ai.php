<?php

/*
|--------------------------------------------------------------------------
| AI Support Assistant
|--------------------------------------------------------------------------
|
| Configuration for the storefront support assistant. The assistant is a
| grounded retrieval-augmented-generation (RAG) agent: it answers only from
| approved knowledge, cites its sources, and escalates to a support ticket
| when it cannot answer.
|
| Chat and embeddings are deliberately separate providers. DeepSeek exposes
| no embeddings endpoint, so vector/hybrid retrieval needs its own service
| (any OpenAI-compatible embeddings API, hosted or self-hosted).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switches
    |--------------------------------------------------------------------------
    */

    // Take the assistant offline without a deploy. When false, the /chat
    // endpoints return 503 and the storefront widget hides itself.
    'enabled' => (bool) env('CHAT_ENABLED', true),

    // Render the widget read-only for design review — never calls the model.
    'preview' => (bool) env('CHAT_PREVIEW', false),

    /*
    |--------------------------------------------------------------------------
    | Chat providers
    |--------------------------------------------------------------------------
    |
    | `default` selects the key from `providers` used for replies. `fallback`
    | is only used when that provider has credentials of its own.
    |
    */

    'default' => env('CHAT_PROVIDER', 'deepseek'),

    'fallback' => env('CHAT_FALLBACK_PROVIDER'),

    'providers' => [

        'deepseek' => [
            'driver' => 'openai_compatible',
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'api_key' => env('DEEPSEEK_API_KEY'),
            // Must support tool calls — not `deepseek-reasoner`.
            'model' => env('CHAT_MODEL', 'deepseek-chat'),
            'timeout' => (int) env('CHAT_TIMEOUT_SECONDS', 60),
        ],

        'openai' => [
            'driver' => 'openai_compatible',
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('CHAT_TIMEOUT_SECONDS', 60),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Chat behaviour
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'max_output_tokens' => (int) env('CHAT_MAX_OUTPUT_TOKENS', 800),
        'temperature' => (float) env('CHAT_TEMPERATURE', 0.2),
        'max_history_messages' => (int) env('CHAT_MAX_HISTORY_MESSAGES', 10),
        // How many model<->tool round trips a single reply may make.
        'max_tool_iterations' => (int) env('CHAT_MAX_TOOL_ITERATIONS', 3),
        'restore_transcript' => (bool) env('CHAT_RESTORE_TRANSCRIPT', true),
        'tickets_enabled' => (bool) env('CHAT_TICKETS_ENABLED', true),
        // Let the assistant look up live catalogue products.
        'products_enabled' => (bool) env('CHAT_PRODUCTS_ENABLED', true),
        // Inject the whole corpus into the prompt. Only sensible for a tiny
        // KB — prefer retrieval. Kept for parity with the reference build.
        'inject_kb' => (bool) env('CHAT_INJECT_KB', false),
        'kb_cache_seconds' => (int) env('CHAT_KB_CACHE_SECONDS', 300),
        'transport' => env('CHAT_TRANSPORT', 'stream'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval (RAG)
    |--------------------------------------------------------------------------
    |
    | Passages are fetched from Typesense. When embeddings are enabled the
    | query is run as a hybrid search (keyword + vector, fused by `alpha`).
    |
    */

    'retrieval' => [
        'collection' => env('CHAT_KB_COLLECTION', 'knowledge'),
        'limit' => (int) env('CHAT_RETRIEVAL_LIMIT', 5),
        // 0 = pure keyword, 1 = pure vector. ~0.3 favours exact terms while
        // still catching paraphrases.
        'alpha' => (float) env('CHAT_RETRIEVAL_ALPHA', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Any OpenAI-compatible embeddings API. Point `base_url`/`model` at your
    | chosen service. For HTAShop's en/de/ur corpus prefer a multilingual
    | model (e.g. a self-hosted BAAI/bge-m3) over an English-only one.
    |
    */

    'embeddings' => [
        'enabled' => (bool) env('EMBEDDINGS_ENABLED', false),
        'base_url' => env('EMBEDDINGS_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('EMBEDDINGS_API_KEY'),
        'model' => env('EMBEDDINGS_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('EMBEDDINGS_DIMENSIONS', 1536),
        'timeout' => (int) env('EMBEDDINGS_TIMEOUT_SECONDS', 30),
        'batch_size' => (int) env('EMBEDDINGS_BATCH_SIZE', 64),
        // How long a query embedding is cached (identical questions are common).
        'query_cache_seconds' => (int) env('EMBEDDINGS_QUERY_CACHE_SECONDS', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota guards
    |--------------------------------------------------------------------------
    |
    | Requests are throttled per visitor, and tokens are budgeted both per
    | visitor and globally per day, so a chatty or abusive client cannot drain
    | the provider quota.
    |
    */

    'limits' => [
        'per_minute' => (int) env('CHAT_RATE_LIMIT_PER_MINUTE', 10),
        'per_day' => (int) env('CHAT_RATE_LIMIT_PER_DAY', 100),
        'visitor_daily_tokens' => (int) env('CHAT_VISITOR_DAILY_TOKENS', 40000),
        'global_daily_tokens' => (int) env('CHAT_GLOBAL_DAILY_TOKENS', 2000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    */

    'redaction' => [
        // Scrub emails, phone numbers and long digit runs before any prompt
        // leaves the API. See App\Services\Ai\PiiRedactor.
        'enabled' => (bool) env('CHAT_REDACT_PII', true),
    ],

];
