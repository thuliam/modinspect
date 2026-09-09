<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

global $config;
$db = Database::connection($config['db']);
$product = $db->query("SELECT id,full_name,slug FROM products WHERE slug='amd-ryzen-7-5700x3d' AND is_active=1 LIMIT 1")->fetch();
$source = $db->query("SELECT id,source_key,name,is_active,is_paused,allowed_collection_method,request_budget_per_day,cost_budget_per_day,risk_level,evidence_quality FROM data_sources WHERE source_key='gemini_google_search' LIMIT 1")->fetch();
$gemini = $config['collection']['providers']['gemini'];

echo json_encode([
    'product' => $product,
    'source' => $source,
    'config' => [
        'live_collection_enabled' => $config['collection']['live_collection_enabled'],
        'paid_provider_calls_enabled' => $config['collection']['paid_provider_calls_enabled'],
        'gemini_provider_enabled' => $gemini['enabled'],
        'gemini_api_key_present' => $gemini['api_key'] !== '',
        'gemini_model' => $gemini['model'],
        'gemini_max_requests_per_run' => $gemini['max_requests_per_run'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
