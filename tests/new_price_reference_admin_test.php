<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\NewPriceReferences;
use App\Services\Auth\AuthService;
use App\Services\Build\BuildCatalog;

/**
 * LS-14 Module 6.5 (OD-2): an admin's new retail price for the Build page. Validated, audited with the actor, the latest row wins, and nothing in
 * the price evidence (observations, snapshots) changes. Runs on the isolated test database inside a rolled-back transaction.
 */
function ok_np(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$db = Database::connection($config['db']);
ok_np($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$db->beginTransaction();
try {
    $u = (new AuthService())->createUser('np-' . bin2hex(random_bytes(4)) . '@example.test', 'NP Admin', 'CorrectHorseBattery99', 'admin');
    $actor = (int)$u['user_id'];
    $pid = (int)$db->query("SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
    $evidence = static fn(): array => [(int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn(), (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn()];
    $before = $evidence();
    $svc = new NewPriceReferences($db);

    $bad = [
        ['0', 'https://shop.example/a', date('Y-m-d'), 'INVALID_PRICE'],
        ['10000001', 'https://shop.example/a', date('Y-m-d'), 'INVALID_PRICE'],
        ['abc', 'https://shop.example/a', date('Y-m-d'), 'INVALID_PRICE'],
        ['1990', 'javascript:alert(1)', date('Y-m-d'), 'INVALID_URL'],
        ['1990', 'ftp://shop.example/a', date('Y-m-d'), 'INVALID_URL'],
        ['1990', 'https://shop.example/a', '2026-02-30', 'INVALID_DATE'],
        ['1990', 'https://shop.example/a', date('Y-m-d', strtotime('+3 days')), 'INVALID_DATE'],
    ];
    foreach ($bad as [$price, $url, $date, $err]) {
        $r = $svc->add($actor, $pid, $price, $url, $date);
        ok_np(!$r['ok'] && $r['error'] === $err, "Rejects $price / $url / $date with $err: " . json_encode($r));
    }
    ok_np($svc->add($actor, 999999999, '1990', 'https://shop.example/a', date('Y-m-d'))['error'] === 'PRODUCT_NOT_FOUND', 'An unknown product is refused');

    $a = $svc->add($actor, $pid, '2,190', 'https://shop.example/a', date('Y-m-d', strtotime('-2 days')), 'first look');
    $b = $svc->add($actor, $pid, '1990', 'https://shop.example/b', date('Y-m-d'));
    ok_np($a['ok'] && $b['ok'], 'Valid rows are stored (a thousands separator is accepted)');
    $audits = (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $actor AND action = 'new_price_reference_created'")->fetchColumn();
    ok_np($audits === 2, 'Every add is audited with the acting admin');

    $refs = (new BuildCatalog($db))->newPriceRefs();
    ok_np(isset($refs[$pid]) && (float)$refs[$pid]['price'] === 1990.0 && $refs[$pid]['source_url'] === 'https://shop.example/b', 'The latest row is the one the Build page uses');
    ok_np($evidence() === $before, 'No observation or snapshot was created or changed');

    ok_np($svc->delete($actor, (int)$b['id'])['ok'] && (float)(new BuildCatalog($db))->newPriceRefs()[$pid]['price'] === 2190.0, 'Deleting the latest falls back to the previous look-up');
    ok_np((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $actor AND action = 'new_price_reference_deleted'")->fetchColumn() === 1, 'Every delete is audited');
    ok_np($svc->delete($actor, 999999999)['error'] === 'NOT_FOUND', 'Deleting an unknown row says so');
    ok_np(count($svc->list()) >= 1 && isset($svc->list()[0]['full_name']), 'The admin list carries the product name');
} finally {
    $db->rollBack();
}
echo "new_price_reference_admin_test: PASS\n";
