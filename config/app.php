<?php
declare(strict_types=1);

return [
    'name' => 'ModInspect',
    'base_url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost/modinspect/public', '/'),
    // closed pilot (docs/PILOT_SCOPE_TH.md): off by default; when on, nothing is indexed and every page carries the pilot notice
    'pilot' => filter_var($_ENV['APP_PILOT'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'modinspect',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
    // LS-14 Module 7 (OD-4): visitor sign-in. A provider is offered only when both values are set (the owner creates the apps, HG-13).
    // Redirect URI to register at each provider: {APP_URL}/account/callback/{google|microsoft|facebook}. Apple is not offered (paid, HG-15).
    'members' => [
        'google' => ['client_id' => $_ENV['MEMBER_GOOGLE_CLIENT_ID'] ?? '', 'client_secret' => $_ENV['MEMBER_GOOGLE_CLIENT_SECRET'] ?? ''],
        'microsoft' => ['client_id' => $_ENV['MEMBER_MICROSOFT_CLIENT_ID'] ?? '', 'client_secret' => $_ENV['MEMBER_MICROSOFT_CLIENT_SECRET'] ?? ''],
        'facebook' => ['client_id' => $_ENV['MEMBER_FACEBOOK_APP_ID'] ?? '', 'client_secret' => $_ENV['MEMBER_FACEBOOK_APP_SECRET'] ?? ''],
        'facebook_graph_version' => $_ENV['MEMBER_FACEBOOK_GRAPH_VERSION'] ?? 'v19.0',
    ],
    // LS-14 Module 9: approved seller listings are shown to everyone only when the owner turns this on (HG-16, marketplace decision)
    'seller' => ['listings_public' => filter_var($_ENV['SELLER_LISTINGS_PUBLIC'] ?? 'false', FILTER_VALIDATE_BOOLEAN)],
    // LS-14 Module 8 (ADR-007): off unless enabled AND keyed. The key is a long random secret in .env (HG-18), never in the repository.
    // LS-21 (R-11, ADR-006 option A): community reports. Off unless enabled AND a real key (32+ characters) is set; the routes answer 404 otherwise.
    'community_reports' => ['enabled' => filter_var($_ENV['COMMUNITY_REPORTS_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN), 'hmac_key' => (string)($_ENV['COMMUNITY_REPORTS_HMAC_KEY'] ?? '')],
    'seller_check' => ['enabled' => filter_var($_ENV['SELLER_CHECK_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN), 'hmac_key' => (string)($_ENV['SELLER_CHECK_HMAC_KEY'] ?? '')],
    'collection' => [
        'live_collection_enabled' => filter_var($_ENV['MODINSPECT_LIVE_COLLECTION_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'paid_provider_calls_enabled' => filter_var($_ENV['MODINSPECT_PAID_PROVIDER_CALLS_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'providers' => [
            'mock' => [
                'enabled' => true,
                'source_key' => 'mock_fixture_provider',
            ],
            'gemini' => [
                'enabled' => filter_var($_ENV['GEMINI_PROVIDER_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                'source_key' => 'gemini_google_search',
                'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
                'model' => $_ENV['GEMINI_MODEL'] ?? 'gemini-1.5-flash',
                'endpoint' => rtrim($_ENV['GEMINI_API_ENDPOINT'] ?? 'https://generativelanguage.googleapis.com/v1beta/models', '/'),
                'max_requests_per_run' => max(1, (int)($_ENV['GEMINI_MAX_REQUESTS_PER_RUN'] ?? 3)),
                'request_timeout_seconds' => max(1, (int)($_ENV['GEMINI_TIMEOUT_SECONDS'] ?? 10)),
            ],
        ],
    ],
];
