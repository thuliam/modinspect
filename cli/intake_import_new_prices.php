<?php
declare(strict_types=1);

/**
 * LS-23 B4: imports B_new_price_references.csv into new_price_references (the Build page's new-price references) through the admin service, with an audit entry
 * per row for the acting admin. DRY RUN unless --apply is given. Run it against the shared DEV database only on the owner's instruction (AGENTS.md).
 * Prints counts only.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/intake_import_new_prices.php <B_new_price_references.csv> --actor=<admin user id> [--apply]
 *
 * Exit: 0 done, 1 the file has problems (nothing written), 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeNewPrices;
use App\Services\Intake\IntakeValidator;

$file = null; $actor = 0; $apply = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--actor=')) { $actor = (int)substr($a, 8); } elseif ($a === '--apply') { $apply = true; } elseif (!str_starts_with($a, '--')) { $file = $a; }
}
if ($file === null || !is_file($file) || $actor < 1) { fwrite(STDERR, "Usage: php cli/intake_import_new_prices.php <file.csv> --actor=<admin user id> [--apply]\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);
$u = $db->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1 AND role = 'admin'");
try { $u->execute(['id' => $actor]); } catch (\PDOException $e) { $u = null; }
if ($u === null || !$u->fetchColumn()) { fwrite(STDERR, "The actor is not an active admin user.\n"); exit(2); }

$ctx = ['now' => new DateTimeImmutable('now'), 'products' => [], 'names' => []];
foreach ($db->query('SELECT full_name, id FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $p) { $ctx['products'][IntakeValidator::norm((string)$p['full_name'])] = 'x'; }
$v = IntakeValidator::validateFile('B', $file, $ctx);
if ($v['header_errors'] !== [] || $v['row_errors'] !== []) {
    echo json_encode(['result' => 'file has problems, nothing written', 'header_errors' => $v['header_errors'], 'error_codes' => $v['codes'], 'first_errors' => array_slice($v['row_errors'], 0, 20)], JSON_PRETTY_PRINT), "\n";
    exit(1);
}
$r = IntakeNewPrices::import($db, $actor, $v['valid'], $apply);
echo json_encode($r + ['mode' => $apply ? 'applied' : 'dry run: nothing was written; add --apply'], JSON_PRETTY_PRINT), "\n";
exit(0);
