<?php
declare(strict_types=1);

if (!defined('MODINSPECT_TESTING')) {
    define('MODINSPECT_TESTING', true);
}

require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Auth\AuthService;
use App\Services\Collection\ImportFieldContract;

function assert_admin_import(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function admin_import_session(int $userId): void
{
    $_SESSION[AuthService::SESSION_USER_ID] = $userId;
    $_SESSION[AuthService::SESSION_LOGIN_AT] = time();
    $_SESSION[AuthService::SESSION_LAST_SEEN_AT] = time();
}

function admin_import_redirect(callable $callable, string $path): void
{
    try {
        $callable();
    } catch (RedirectException $e) {
        assert_admin_import($e->path === $path, 'Expected redirect to ' . $path . ', got ' . $e->path);
        return;
    }
    throw new RuntimeException('Expected redirect to ' . $path);
}

function admin_import_render(callable $callable): string
{
    ob_start();
    try {
        $callable();
    } catch (RedirectException $e) {
        ob_end_clean();
        throw $e;
    }
    return (string)ob_get_clean();
}

function admin_import_temp_csv(array $record): string
{
    $dir = ROOT_PATH . '/storage/import/test';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = $dir . '/admin-import-workflow-' . bin2hex(random_bytes(4)) . '.csv';
    $out = fopen($path, 'wb');
    if ($out === false) {
        throw new RuntimeException('Cannot write import workflow fixture');
    }
    fputcsv($out, ImportFieldContract::CSV_HEADERS);
    fputcsv($out, array_map(static fn(string $header): string => (string)($record[$header] ?? ''), ImportFieldContract::CSV_HEADERS));
    fclose($out);
    return $path;
}

global $config;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];

$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $auth = new AuthService();
    $admin = $auth->createUser("import-admin-{$suffix}@example.test", 'Import Admin', 'CorrectHorseBattery99', 'admin');
    assert_admin_import(($admin['ok'] ?? false) === true, 'Admin fixture user should be created');
    admin_import_session((int)$admin['user_id']);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs';
    $page = admin_import_render(fn() => (new AdminController())->jobs());
    assert_admin_import(str_contains($page, 'Download CSV Template'), 'Imports page must expose CSV template download');
    assert_admin_import(str_contains($page, 'Download JSON Template'), 'Imports page must expose JSON template download');
    assert_admin_import(str_contains($page, 'Import Format / Field Reference'), 'Imports page must expose field guide');
    assert_admin_import(str_contains($page, 'Confirm Import unlocks after a Dry Run'), 'Confirm Import must be gated before dry-run');

    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/template.csv';
    $csvTemplate = admin_import_render(fn() => (new AdminController())->importCsvTemplate());
    assert_admin_import(str_contains($csvTemplate, 'source_item_id') && str_contains($csvTemplate, 'EXAMPLE-ITEM-001'), 'CSV template content mismatch');

    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/template.json';
    $jsonTemplate = admin_import_render(fn() => (new AdminController())->importJsonTemplate());
    assert_admin_import(str_contains($jsonTemplate, '"records"') && str_contains($jsonTemplate, 'EXAMPLE / TEMPLATE ONLY'), 'JSON template content mismatch');

    $record = ImportFieldContract::exampleRecord();
    $record['source'] = 'priceza';
    $record['source_type'] = 'marketplace';
    $record['source_domain'] = 'www.priceza.com';
    $record['source_search_url'] = 'https://www.priceza.com/s/amd-ryzen-7-5700x3d';
    $record['source_listing_url'] = 'https://www.priceza.com/r/redirect?id=admin-import-' . $suffix;
    $record['source_url'] = $record['source_listing_url'];
    $record['source_item_id'] = 'admin-import-' . $suffix;
    $record['external_ref'] = 'admin-import-ref-' . $suffix;
    $record['merchant'] = 'Workflow Fixture Store';
    $record['merchant_target_url'] = 'https://merchant.example.test/item/admin-import-' . $suffix;
    $record['listing_title'] = 'AMD Ryzen 7 5700X3D used CPU';
    $record['title'] = $record['listing_title'];
    $record['listing_text'] = 'Used AMD Ryzen 7 5700X3D single CPU, working normally, asking 7500 THB.';
    $record['asking_price'] = '7500';
    $record['displayed_price'] = '7500';
    $record['observed_at'] = '2026-09-09 12:00:00';
    $record['evidence_snapshot_json'] = json_encode([
        'source_item_id' => $record['source_item_id'],
        'listing_title' => $record['listing_title'],
        'asking_price' => $record['asking_price'],
        'merchant' => $record['merchant'],
        'observed_at' => $record['observed_at'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $record['evidence_hash'] = hash('sha256', (string)$record['evidence_snapshot_json']);
    $record['notes'] = 'Admin import workflow fixture';
    $record['ingestion_note'] = 'Admin import workflow test fixture';
    $record['product_hint'] = '5700X3D';

    $uploadPath = admin_import_temp_csv($record);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/import-dry-run';
    $_POST = ['_token' => Csrf::token(), 'dataset' => 'test', 'limit' => '10'];
    $_FILES = ['evidence_file' => ['name' => 'owner-template-filled.csv', 'type' => 'text/csv', 'tmp_name' => $uploadPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($uploadPath)]];
    $before = (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn();
    admin_import_redirect(fn() => (new AdminController())->importDryRun(), '/admin/collector-jobs');
    assert_admin_import(isset($_SESSION['admin_import_pending']), 'Successful dry-run must create pending confirm context');
    assert_admin_import((int)($_SESSION['admin_import_summary']['valid_rows'] ?? 0) === 1, 'Dry-run should report one eligible row');
    assert_admin_import((int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn() === $before, 'Dry-run must not create review rows');

    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/import-confirm';
    $_POST = ['_token' => Csrf::token()];
    $_FILES = [];
    admin_import_redirect(fn() => (new AdminController())->importConfirm(), '/admin/collector-jobs');
    $after = (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn();
    assert_admin_import($after === $before + 1, 'Confirm Import should create one review observation');
    assert_admin_import(!isset($_SESSION['admin_import_pending']), 'Confirm Import must clear pending context');
    assert_admin_import((int)($_SESSION['admin_import_summary']['reviews_created'] ?? 0) === 1, 'Confirm Import should create one review queue row');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs';
    $afterPage = admin_import_render(fn() => (new AdminController())->jobs());
    assert_admin_import(str_contains($afterPage, 'Confirm Import unlocks after a Dry Run'), 'Confirm gate should close after import');

    echo "Admin import workflow test passed\n";
    echo "CSV/JSON templates: ok\n";
    echo "Dry-run gate: ok\n";
    echo "Confirm to review queue: ok\n";
} finally {
    $db->rollBack();
}
