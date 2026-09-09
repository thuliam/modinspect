<?php
declare(strict_types=1);

return [
    'name' => 'Used PC Price Radar',
    'base_url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost/modinspect/public', '/'),
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'modinspect',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
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
