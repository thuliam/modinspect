<?php
declare(strict_types=1);

// The public address is APP_URL's scheme and host plus the folder the web server actually serves public/ from, found by comparing the real public
// folder with the server's document root. So links and assets stay correct whether the host serves .../public (document root not yet set) or the
// site root (document root = public), whatever APP_URL's path says.
$appUrl = rtrim((string)($_ENV['APP_URL'] ?? 'http://localhost/modinspect/public'), '/');
$docRoot = PHP_SAPI !== 'cli' ? realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) : false;
$pubDir = realpath(dirname(__DIR__) . '/public');
if ($docRoot !== false && $pubDir !== false) {
    $d = rtrim(str_replace('\\', '/', $docRoot), '/');
    $p = rtrim(str_replace('\\', '/', $pubDir), '/');
    $origin = parse_url($appUrl);
    if (!empty($origin['scheme']) && !empty($origin['host']) && strncasecmp($p, $d, strlen($d)) === 0 && (strlen($p) === strlen($d) || $p[strlen($d)] === '/')) {
        $sub = substr($p, strlen($d));
        // when the host rewrites every request into public/ internally, the visible URL has no such folder: the base is then empty
        $reqPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        if ($sub !== '' && $reqPath !== $sub && strncmp($reqPath, $sub . '/', strlen($sub) + 1) !== 0) { $sub = ''; }
        $appUrl = $origin['scheme'] . '://' . $origin['host'] . (isset($origin['port']) ? ':' . $origin['port'] : '') . $sub;
    }
}

// LS-85: when the page is opened by another address than APP_URL's host (localhost instead of the LAN IP), links must stay on the address the browser used,
// or the page loses its styles (Content-Security-Policy allows only its own origin). Only loopback names and hosts listed in APP_ALLOWED_HOSTS qualify, so a
// forged Host header can never change the public address (canonical links, sitemap, manifest).
if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $reqHost = (string)$_SERVER['HTTP_HOST'];
    $reqName = strtolower((string)(parse_url('//' . $reqHost, PHP_URL_HOST) ?? ''));
    $allowed = array_merge(['localhost', '127.0.0.1', '[::1]'], array_filter(array_map('trim', explode(',', strtolower((string)($_ENV['APP_ALLOWED_HOSTS'] ?? ''))))));
    $cur = parse_url($appUrl);
    if ($reqName !== '' && in_array($reqName, $allowed, true) && strtolower((string)($cur['host'] ?? '')) !== $reqName) {
        $appUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $reqHost . (string)($cur['path'] ?? '');
    }
}

return [
    'name' => 'ModInspect',
    'base_url' => $appUrl,
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
    // LS-24 M9: community forum (threads, replies, reviews). Off unless enabled; every route answers 404 and no page links to it while off.
    'community_forum' => ['enabled' => filter_var($_ENV['COMMUNITY_FORUM_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN)],
    // LS-86: browser push for price-change alerts. Off unless PUSH_ENABLED=true; no sender is wired yet (a Web Push library needs owner approval).
    'push' => ['enabled' => filter_var($_ENV['PUSH_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN)],
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
