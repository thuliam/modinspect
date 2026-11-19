<?php
declare(strict_types=1);

/**
 * LS-23 B7: imports F_sold_prices.csv as PENDING sold-price candidates for the review queue (never approved, never a snapshot, never a price on its own).
 * DRY RUN unless --apply is given. Shared DEV database only on the owner's instruction. Prints counts only.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/intake_import_sold_prices.php <F_sold_prices.csv> --actor=<admin user id> [--apply]
 *
 * Exit: 0 done, 1 the file has problems (nothing written), 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeSoldPrices;
use App\Services\Intake\IntakeValidator;
use App\Services\ProductResolver\ProductResolver;

$file = null; $actor = 0; $apply = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--actor=')) { $actor = (int)substr($a, 8); } elseif ($a === '--apply') { $apply = true; } elseif (!str_starts_with($a, '--')) { $file = $a; }
}
if ($file === null || !is_file($file) || $actor < 1) { fwrite(STDERR, "Usage: php cli/intake_import_sold_prices.php <file.csv> --actor=<admin user id> [--apply]\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);
$u = $db->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1 AND role = 'admin'");
$u->execute(['id' => $actor]);
if (!$u->fetchColumn()) { fwrite(STDERR, "The actor is not an active admin user.\n"); exit(2); }

$v = IntakeValidator::validateFile('F', $file, ['now' => new DateTimeImmutable('now')]);
if ($v['header_errors'] !== [] || $v['row_errors'] !== []) {
    echo json_encode(['result' => 'file has problems, nothing written', 'header_errors' => $v['header_errors'], 'error_codes' => $v['codes'], 'first_errors' => array_slice($v['row_errors'], 0, 20)], JSON_PRETTY_PRINT), "\n";
    exit(1);
}
$resolver = new ProductResolver((new \App\Models\CollectionPipeline())->catalog());
$r = IntakeSoldPrices::import($db, $actor, $v['valid'], $resolver, $apply);
echo json_encode($r + ['mode' => $apply ? 'applied: rows wait in the review queue' : 'dry run: nothing was written; add --apply'], JSON_PRETTY_PRINT), "\n";
exit(0);
