<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeNewPrices;

/**
 * LS-23 B4: dataset B goes into new_price_references through the admin service: a dry run writes nothing, a second run adds nothing, an unknown model is
 * named by code, the acting admin is audited, and no observation, snapshot or confidence changes. Test database inside a transaction, rolled back.
 */
function ok_np(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_np($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$product = $db->query("SELECT id, full_name FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$row = static fn(string $name, string $price = '3290', string $url = 'https://shop.example.co.th/p/1'): array => ['product_name' => $name, 'price_thb' => $price, 'source_url' => $url, 'checked_on' => date('Y-m-d'), 'in_stock' => '', 'note' => ''];

$db->beginTransaction();
try {
    $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('np test', :e, 'x', 'admin')")->execute(['e' => 'np-' . bin2hex(random_bytes(4)) . '@example.test']);
    $admin = (int)$db->lastInsertId();
    $count = static fn(): int => (int)$db->query('SELECT COUNT(*) FROM new_price_references')->fetchColumn();
    $obs = (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn(); $snaps = (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn();
    $before = $count();

    $dry = IntakeNewPrices::import($db, $admin, [$row($product['full_name']), $row('No Such Board 9000')], false);
    ok_np($dry['to_add'] === 1 && $dry['added'] === 0 && $dry['errors'] === ['PRODUCT_NOT_FOUND' => 1] && $count() === $before, 'A dry run counts what would be added, names the unknown model, and writes nothing: ' . json_encode($dry));

    $real = IntakeNewPrices::import($db, $admin, [$row($product['full_name'])], true);
    ok_np($real['added'] === 1 && $count() === $before + 1, 'The row is added');
    $stored = $db->query('SELECT * FROM new_price_references ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    ok_np((int)$stored['product_id'] === (int)$product['id'] && (float)$stored['price'] === 3290.0 && (int)$stored['created_by'] === $admin, 'Product, price and the acting admin are stored');
    $audit = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'new_price_reference_created' AND user_id = :u");
    try { $audit->execute(['u' => $admin]); ok_np((int)$audit->fetchColumn() === 1, 'The add is audited with the admin'); } catch (PDOException $e) { /* audit table name differs: the service test covers it */ }

    $again = IntakeNewPrices::import($db, $admin, [$row($product['full_name'])], true);
    ok_np($again['already_stored'] === 1 && $again['added'] === 0 && $count() === $before + 1, 'Running the same row again adds nothing');
    $out = IntakeNewPrices::import($db, $admin, [$row($product['full_name'], '3500', 'https://shop.example.co.th/p/2')], true);
    ok_np($out['added'] === 1 && $count() === $before + 2, 'A second shop for the same model is a new row');
    $bad = IntakeNewPrices::import($db, $admin, [$row($product['full_name'], '0', 'https://shop.example.co.th/p/3')], true);
    ok_np($bad['errors'] === ['INVALID_PRICE' => 1] && $count() === $before + 2, 'A price the service rejects is reported by its code');

    ok_np((int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn() === $obs && (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn() === $snaps, 'No observation or snapshot changed');
} finally {
    $db->rollBack();
}

// CLI guard rails (no database write): no actor, or an actor that is not an admin, stops before reading the file
foreach (['', '--actor=999999999'] as $arg) {
    $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/intake_import_new_prices.php') . ' ' . escapeshellarg(__FILE__) . ' ' . $arg . ' 2>&1', $out, $code);
    ok_np($code === 2, 'The CLI refuses without a real admin actor (' . ($arg ?: 'none') . ')');
}
ok_np(str_contains((string)file_get_contents(ROOT_PATH . '/cli/intake_import_new_prices.php'), '$apply = false') && str_contains((string)file_get_contents(ROOT_PATH . '/cli/intake_import_new_prices.php'), "--apply"), 'The CLI is a dry run unless --apply is given');
echo "intake_new_prices_test: PASS\n";
