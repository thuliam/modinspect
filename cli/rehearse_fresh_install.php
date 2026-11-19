<?php
declare(strict_types=1);

/**
 * LS-17 D1 / LS-19 A1: build an EMPTY database the way a new host gets it, and report the first statement that fails.
 *
 * Fresh-install rule (found by the first CI run, 2026-11-19): database/schema.sql is the FULL current structure (every table and column up to
 * the newest migration; tests/fresh_install_schema_test.php keeps it that way), so a new host runs
 *   1. database/schema.sql
 *   2. database/seed.sql            development/test: all of it; production (--production): ONLY its catalog rows (categories, brands, products,
 *                                   aliases), never its fixture prices, histories, demo shop or example sources (they would show as market prices)
 *   3. the DATA statements of every migration in order (INSERT / UPDATE / DELETE / REPLACE); their CREATE / ALTER / DROP are already in schema.sql
 * An existing database (the shared DEV, the current host) keeps using cli/migrate.php on the new migrations only; this script is for a new one.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/rehearse_fresh_install.php modinspect_rehearsal [--production] [--allow-mysql]
 *
 * --production   seed.sql catalog rows only, and require a usable catalog with NO price snapshot (nothing priced until reviewed evidence exists)
 * --allow-mysql  run on a local MySQL (the studio has MySQL 8.4); the host and CI run MariaDB 10.6, which stays the reference
 * Safety: only a database whose name ends in "_rehearsal" on 127.0.0.1/localhost (anything else is refused, so it can never reach the shared DEV or
 * production). It drops and recreates that one database. Credentials come from the env file and are never printed. "CREATE DATABASE" and "USE"
 * statements are never executed. Exit 0 when everything applied, 1 on the first failure, 2 on a refused or bad argument.
 */
$args = array_slice($argv, 1);
$production = in_array('--production', $args, true); $allowMysql = in_array('--allow-mysql', $args, true);
$name = (string)(array_values(array_filter($args, static fn(string $a): bool => !str_starts_with($a, '--')))[0] ?? '');
if (!preg_match('/^[a-z0-9_]{1,48}_rehearsal$/', $name)) { fwrite(STDERR, "Usage: php cli/rehearse_fresh_install.php <name>_rehearsal [--production] [--allow-mysql]\n"); exit(2); }

require dirname(__DIR__) . '/app/bootstrap.php';
global $config;
$db = $config['db'];
if (!in_array((string)$db['host'], ['127.0.0.1', 'localhost'], true)) { fwrite(STDERR, "Refused: the database server in the env file is not local\n"); exit(2); }

/** split a SQL file into statements (same quote-aware rule as cli/migrate.php) */
function rehearsal_statements(string $sql): array
{
    $out = []; $buf = ''; $quote = null; $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i]; $prev = $i > 0 ? $sql[$i - 1] : '';
        if (($c === "'" || $c === '"') && $prev !== '\\') { $quote = $quote === $c ? null : ($quote ?? $c); }
        if ($c === ';' && $quote === null) { if (trim($buf) !== '') { $out[] = trim($buf); } $buf = ''; continue; }
        $buf .= $c;
    }
    if (trim($buf) !== '') { $out[] = trim($buf); }
    return $out;
}

/** the statement without leading comment lines */
function rehearsal_body(string $st): string { return trim((string)preg_replace('/^(\s*(--[^\n]*|#[^\n]*)?\n)+/', '', $st . "\n")); }

$pdo = new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';charset=utf8mb4', (string)$db['username'], (string)$db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
if (stripos($version, 'mariadb') === false) {
    if (!$allowMysql) { fwrite(STDERR, "Refused: this server is MySQL $version; the host runs MariaDB 10.6 (CI runs it there, or pass --allow-mysql for a local look)\n"); exit(2); }
    echo "note  MySQL $version: a local look only, MariaDB 10.6 in CI is the reference\n";
}
$pdo->exec("DROP DATABASE IF EXISTS `$name`");
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");

/** seed.sql tables that are real catalog, not fixtures */
const CATALOG_TABLES = ['product_categories', 'brands', 'products', 'product_aliases'];
$root = dirname(__DIR__);
$steps = [['database/schema.sql', 'all'], ['database/seed.sql', $production ? 'catalog' : 'all']];
foreach (glob($root . '/database/migrations/*.sql') ?: [] as $f) { $steps[] = ['database/migrations/' . basename($f), 'data']; }

$started = microtime(true); $count = 0;
foreach ($steps as [$label, $mode]) {
    $n = 0; $skipped = 0;
    foreach (rehearsal_statements((string)file_get_contents($root . '/' . $label)) as $st) {
        $body = rehearsal_body($st);
        if ($body === '' || preg_match('/^(CREATE\s+DATABASE|USE\s)/i', $body)) { continue; }
        if ($mode === 'data' && !preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $body)) { $skipped++; continue; }
        if ($mode === 'catalog' && !preg_match('/^INSERT\s+(IGNORE\s+)?INTO\s+`?(' . implode('|', CATALOG_TABLES) . ')`?\s/i', $body)) { $skipped++; continue; }
        try { $pdo->exec($st); $n++; }
        catch (PDOException $e) { echo "FAIL  $label statement " . ($n + $skipped + 1) . ': ' . substr((string)preg_replace('/\s+/', ' ', $body), 0, 120) . "\n      " . $e->getMessage() . "\n"; exit(1); }
    }
    $count += $n;
    echo sprintf("  ok    %-58s %4d run%s\n", $label, $n, $skipped ? ($mode === "catalog" ? ", $skipped fixture statements left out" : ", $skipped structure skipped (in schema.sql)") : '');
}
$tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($name))->fetchColumn();
$products = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$specs = (int)$pdo->query('SELECT COUNT(*) FROM product_reference_specs')->fetchColumn();
$categories = (int)$pdo->query('SELECT COUNT(*) FROM product_categories')->fetchColumn();
// legacy snapshots from the early migrations are never public (provenance legacy_unavailable); a production install must start with no PUBLIC price
$priced = (int)$pdo->query("SELECT COUNT(*) FROM price_indices WHERE COALESCE(provenance_status,'legacy_unavailable') <> 'legacy_unavailable'")->fetchColumn();
if ($production && $priced > 0) { echo "FAIL  a production install must start with no price snapshot, found $priced public snapshot(s) (fixture prices leaked)\n"; exit(1); }
if ($products === 0 || $specs === 0 || $categories < 8) { echo "FAIL  the database was built but the catalog is not usable ($products products, $specs specs, $categories categories)\n"; exit(1); }
echo sprintf("\nREHEARSAL PASS (%s): %d steps, %d statements, %d tables, %d products, %d manufacturer specs, %d categories, %.1f s (database %s)\n",
    $production ? 'production, no fixtures' : 'development, with fixtures', count($steps), $count, $tables, $products, $specs, $categories, microtime(true) - $started, $name);
exit(0);
