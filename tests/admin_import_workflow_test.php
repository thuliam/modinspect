<?php
declare(strict_types=1);

if (!defined('MODINSPECT_TESTING')) {
    define('MODINSPECT_TESTING', true);
}

if (!defined('ROOT_PATH')) {
    require __DIR__ . '/bootstrap.php';
}

use App\Controllers\AdminController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Admin\AdminDataTableService;
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

function admin_import_jobs_datatable_assert(string $baseUrl): void
{
    $service = new AdminDataTableService();
    $first = $service->jobs(['draw' => '1', 'start' => '0', 'length' => '1'], $baseUrl, ['role' => 'admin', 'is_active' => 1]);
    assert_admin_import((int)$first['recordsTotal'] >= 1, 'Jobs DataTable total should include imported test job');
    assert_admin_import(count($first['data']) === 1, 'Jobs DataTable must paginate to requested length');

    $search = $service->jobs(['draw' => '2', 'start' => '0', 'length' => '10', 'search' => ['value' => 'offline_file_import']], $baseUrl, ['role' => 'admin', 'is_active' => 1]);
    assert_admin_import((int)$search['recordsFiltered'] >= 1, 'Jobs DataTable search should find offline import jobs');

    $pageTwo = $service->jobs(['draw' => '3', 'start' => '1', 'length' => '1'], $baseUrl, ['role' => 'admin', 'is_active' => 1]);
    assert_admin_import((int)$pageTwo['recordsTotal'] === (int)$first['recordsTotal'], 'Jobs DataTable pagination should preserve total count');

    $sorted = $service->jobs(['draw' => '4', 'start' => '0', 'length' => '5', 'order' => [['column' => '4', 'dir' => 'asc']]], $baseUrl, ['role' => 'admin', 'is_active' => 1]);
    assert_admin_import((int)$sorted['recordsFiltered'] >= 1, 'Jobs DataTable sorting should return rows');

    $filtered = $service->jobs(['draw' => '5', 'start' => '0', 'length' => '10', 'status' => 'completed', 'dataset' => 'MOCK_TEST', 'run_id' => 'offline-import'], $baseUrl, ['role' => 'admin', 'is_active' => 1]);
    assert_admin_import((int)$filtered['recordsFiltered'] >= 1, 'Jobs DataTable filters should find completed TEST/MOCK import jobs');
}

global $config;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];

$db = Database::connection($config['db']);
$db->beginTransaction();
$csvPath = null;
$legacyUpload = null;

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

    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/import-confirm';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_token' => Csrf::token()];
    $_FILES = [];
    unset($_SESSION['admin_import_pending']);
    admin_import_redirect(fn() => (new AdminController())->importConfirm(), '/admin/collector-jobs');
    assert_admin_import(!isset($_SESSION['admin_import_pending']), 'Confirm without Dry Run must stay blocked');
    assert_admin_import(str_contains((string)($_SESSION['flash']['message'] ?? ''), 'Dry Run'), 'Confirm without Dry Run should explain the gate');

    $records = [];
    for ($i = 1; $i <= 3; $i++) {
        $row = ImportFieldContract::exampleRecord();
        $item = 'TEST-IMPORT-' . $suffix . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $row['source'] = 'test_import_fixture';
        $row['source_type'] = 'marketplace';
        $row['source_domain'] = 'test-import.local';
        $row['source_search_url'] = 'https://test-import.local/search?q=5700x3d';
        $row['source_listing_url'] = 'https://test-import.local/listing/' . $item;
        $row['source_url'] = $row['source_listing_url'];
        $row['source_item_id'] = $item;
        $row['external_ref'] = 'TEST-IMPORT-REF-' . $suffix . '-' . $i;
        $row['source_reference'] = $row['external_ref'];
        $row['external_listing_id'] = $item;
        $row['merchant'] = 'TEST Import Store ' . $i;
        $row['merchant_target_url'] = 'https://merchant.test-import.local/item/' . $item;
        $row['listing_title'] = 'AMD Ryzen 7 5700X3D TEST import row ' . $i;
        $row['title'] = $row['listing_title'];
        $row['listing_text'] = 'TEST/MOCK fixture only. Used AMD Ryzen 7 5700X3D single CPU, working normally, asking ' . (7400 + $i * 100) . ' THB.';
        $row['asking_price'] = (string)(7400 + $i * 100);
        $row['displayed_price'] = $row['asking_price'];
        $row['observed_at'] = '2026-09-09 12:0' . $i . ':00';
        $row['evidence_snapshot_json'] = json_encode([
            'dataset' => 'TEST/MOCK',
            'source_item_id' => $row['source_item_id'],
            'listing_title' => $row['listing_title'],
            'asking_price' => $row['asking_price'],
            'merchant' => $row['merchant'],
            'observed_at' => $row['observed_at'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $row['evidence_hash'] = hash('sha256', (string)$row['evidence_snapshot_json']);
        $row['notes'] = 'TEST/MOCK Admin import workflow fixture only';
        $row['ingestion_note'] = 'TEST/MOCK workflow proof; not REAL evidence';
        $row['product_hint'] = '5700X3D';
        $records[] = $row;
    }

    $csvPath = ROOT_PATH . '/storage/import/test/admin-import-workflow-' . $suffix . '.csv';
    $out = fopen($csvPath, 'wb');
    if ($out === false) {
        throw new RuntimeException('Cannot write multi-row import workflow fixture');
    }
    fputcsv($out, ImportFieldContract::CSV_HEADERS);
    foreach ($records as $row) {
        fputcsv($out, array_map(static fn(string $header): string => (string)($row[$header] ?? ''), ImportFieldContract::CSV_HEADERS));
    }
    fclose($out);

    $record = $records[0];
    $legacyUpload = admin_import_temp_csv($record);
    assert_admin_import(array_keys($record) === ImportFieldContract::CSV_HEADERS, 'Generated test file must match template field contract');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/import-dry-run';
    $_POST = ['_token' => Csrf::token(), 'dataset' => 'test', 'limit' => '10'];
    $_FILES = ['evidence_file' => ['name' => 'owner-template-filled.csv', 'type' => 'text/csv', 'tmp_name' => $csvPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($csvPath)]];
    $before = (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn();
    $approvedBefore = (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status='approved'")->fetchColumn();
    $snapshotsBefore = (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn();
    admin_import_redirect(fn() => (new AdminController())->importDryRun(), '/admin/collector-jobs');
    assert_admin_import(isset($_SESSION['admin_import_pending']), 'Successful dry-run must create pending confirm context');
    assert_admin_import((int)($_SESSION['admin_import_summary']['records_scanned'] ?? 0) === 3, 'Dry-run should scan three rows');
    assert_admin_import((int)($_SESSION['admin_import_summary']['valid_rows'] ?? 0) === 3, 'Dry-run should report three eligible rows');
    assert_admin_import((int)($_SESSION['admin_import_summary']['invalid_rows'] ?? 0) === 0, 'Dry-run should report zero invalid rows');
    assert_admin_import((int)($_SESSION['admin_import_summary']['duplicate_rows'] ?? 0) === 0, 'Dry-run should report zero duplicates');
    assert_admin_import((int)($_SESSION['admin_import_summary']['estimated_candidate_count'] ?? 0) === 3, 'Dry-run should report three rows that would import');
    assert_admin_import((int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn() === $before, 'Dry-run must not create review rows');

    $pendingFile = (string)$_SESSION['admin_import_pending']['file'];
    file_put_contents($pendingFile, "\n#changed", FILE_APPEND);
    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/import-confirm';
    $_POST = ['_token' => Csrf::token()];
    $_FILES = [];
    admin_import_redirect(fn() => (new AdminController())->importConfirm(), '/admin/collector-jobs');
    assert_admin_import(!isset($_SESSION['admin_import_pending']), 'Changed dry-run file should clear pending import context');
    assert_admin_import((int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn() === $before, 'Changed dry-run file must not import');

    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/import-dry-run';
    $_POST = ['_token' => Csrf::token(), 'dataset' => 'test', 'limit' => '10'];
    $_FILES = ['evidence_file' => ['name' => 'owner-template-filled.csv', 'type' => 'text/csv', 'tmp_name' => $csvPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($csvPath)]];
    admin_import_redirect(fn() => (new AdminController())->importDryRun(), '/admin/collector-jobs');
    assert_admin_import(isset($_SESSION['admin_import_pending']), 'Second successful dry-run must recreate pending confirm context');

    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs/import-confirm';
    $_POST = ['_token' => Csrf::token()];
    $_FILES = [];
    admin_import_redirect(fn() => (new AdminController())->importConfirm(), '/admin/collector-jobs');
    $after = (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn();
    assert_admin_import($after === $before + 3, 'Confirm Import should create three review observations');
    assert_admin_import(!isset($_SESSION['admin_import_pending']), 'Confirm Import must clear pending context');
    assert_admin_import((int)($_SESSION['admin_import_summary']['reviews_created'] ?? 0) === 3, 'Confirm Import should create three review queue rows');

    $sourceId = (int)$db->query("SELECT id FROM data_sources WHERE source_key='offline_test_test_import_local' LIMIT 1")->fetchColumn();
    assert_admin_import($sourceId > 0, 'Offline TEST import source should exist');
    $queueCount = (int)$db->query("SELECT COUNT(*) FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id={$sourceId} AND o.verified_status='pending'")->fetchColumn();
    $approvedImported = (int)$db->query("SELECT COUNT(*) FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id={$sourceId} AND o.verified_status='approved'")->fetchColumn();
    assert_admin_import($queueCount === 3, 'Imported TEST rows should enter Review Queue as pending');
    assert_admin_import($approvedImported === 0, 'Imported TEST rows must not be auto-approved');
    assert_admin_import((int)$db->query("SELECT COUNT(*) FROM data_sources WHERE source_key LIKE 'offline_real_test_import%'")->fetchColumn() === 0, 'TEST workflow must not create REAL source');
    assert_admin_import((int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status='approved'")->fetchColumn() === $approvedBefore, 'Public-eligible approved observations must remain unchanged');
    assert_admin_import((int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn() === $snapshotsBefore, 'Price snapshots must remain unchanged');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs';
    $afterPage = admin_import_render(fn() => (new AdminController())->jobs());
    assert_admin_import(str_contains($afterPage, 'Confirm Import unlocks after a Dry Run'), 'Confirm gate should close after import');
    admin_import_jobs_datatable_assert((string)$config['base_url']);

    echo "Admin import workflow test passed\n";
    echo "Active DB: {$config['db']['host']}/{$config['db']['database']}\n";
    echo "CSV/JSON templates: ok\n";
    echo "Safe TEST rows: 3\n";
    echo "Dry-run summary: scanned=3 eligible=3 invalid=0 duplicates=0\n";
    echo "Confirm gates: without-dry-run blocked, changed-context blocked, same-context passed\n";
    echo "Confirm to review queue: 3 pending TEST/MOCK rows\n";
    echo "Jobs DataTable: search/pagination/sorting/filters ok\n";
} finally {
    $db->rollBack();
    foreach ([$csvPath, $legacyUpload] as $path) {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
    foreach (glob(ROOT_PATH . '/storage/import_uploads/admin-import-*.csv') ?: [] as $path) {
        if (is_file($path) && filemtime($path) >= time() - 300) {
            @unlink($path);
        }
    }
}
