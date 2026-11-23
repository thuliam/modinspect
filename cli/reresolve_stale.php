<?php
declare(strict_types=1);

// LS-76: re-resolve stored unresolved rows against the CURRENT catalog (new models, generic spec classes). Dry run unless --apply.
//   php cli/reresolve_stale.php                                    report only
//   php cli/reresolve_stale.php --apply --expect=N [--limit=500]   creates PENDING observations; refuses unless the dry run would create exactly N
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\CollectionPipeline;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Research\StaleReResolver;
use App\Services\Validation\ObservationValidator;

global $config;
$o = getopt('', ['apply', 'expect::', 'limit::']);
$db = Database::connection($config['db']);
$resolver = new ProductResolver((new CollectionPipeline())->catalog());
$validator = new ObservationValidator(false);
$rep = StaleReResolver::report($db, $resolver, $validator);
$would = (int)($rep['outcomes']['would_create'] ?? 0);
if (isset($o['apply'])) {
    if (!isset($o['expect']) || (int)$o['expect'] !== $would) { fwrite(STDERR, "Refused: --expect must equal the dry-run count ($would).\n"); exit(2); }
    $rep = StaleReResolver::report($db, $resolver, $validator, true, max(1, (int)($o['limit'] ?? 500)));
}
echo json_encode($rep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
