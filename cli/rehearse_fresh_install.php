<?php
declare(strict_types=1);

/**
 * LS-17 D1: rebuild an EMPTY database the way a new host would get it (database/schema.sql, database/seed.sql, then every migration in order)
 * and report the first statement that fails. It proves the files in the repository still build a working database from nothing.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/rehearse_fresh_install.php modinspect_rehearsal
 *
 * Safety: it only ever touches a database whose name ends in "_rehearsal" on 127.0.0.1/localhost (refuses anything else, so it can never reach
 * the shared DEV or a production database). It drops and recreates that one database. The server credentials come from the env file and are never printed.
 * "CREATE DATABASE" and "USE" lines inside the SQL files are skipped, so the files can never switch to another database.
 * Exit 0 when everything applied, 1 on the first failure, 2 on a refused or bad argument.
 */
$name = (string)($argv[1] ?? '');
if (!preg_match('/^[a-z0-9_]{1,48}_rehearsal$/', $name)) { fwrite(STDERR, "Usage: php cli/rehearse_fresh_install.php <name>_rehearsal\n"); exit(2); }

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

$pdo = new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';charset=utf8mb4', (string)$db['username'], (string)$db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// the host runs MariaDB 10.6 and the migrations use its syntax (ADD COLUMN IF NOT EXISTS); on MySQL the rehearsal would fail for a reason the host never sees
$version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
if (stripos($version, 'mariadb') === false) { fwrite(STDERR, "Refused: this server is MySQL $version; the host runs MariaDB 10.6 (CI job \"Fresh install rehearsal\" runs it there)\n"); exit(2); }
$pdo->exec("DROP DATABASE IF EXISTS `$name`");
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");

$root = dirname(__DIR__);
$files = array_merge([$root . '/database/schema.sql', $root . '/database/seed.sql'], glob($root . '/database/migrations/*.sql') ?: []);
$started = microtime(true); $count = 0;
foreach ($files as $file) {
    $label = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $root)) + 1);
    // a comment line before a statement stays part of it; only a statement that IS a CREATE DATABASE or USE line is skipped
    $n = 0;
    foreach (rehearsal_statements((string)file_get_contents($file)) as $st) {
        $body = trim((string)preg_replace('/^(\s*(--[^\n]*|#[^\n]*)\n)+/', '', $st));
        if ($body === '' || preg_match('/^(CREATE\s+DATABASE|USE\s)/i', $body)) { continue; }
        try { $pdo->exec($st); $n++; }
        catch (PDOException $e) { echo "FAIL  $label statement " . ($n + 1) . ': ' . substr(preg_replace('/\s+/', ' ', $body), 0, 120) . "\n      " . $e->getMessage() . "\n"; exit(1); }
    }
    $count += $n;
    echo sprintf("  ok    %-58s %4d statements\n", $label, $n);
}
$tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($name))->fetchColumn();
$products = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
echo sprintf("\nREHEARSAL PASS: %d files, %d statements, %d tables, %d products, %.1f s (database %s)\n", count($files), $count, $tables, $products, microtime(true) - $started, $name);
exit(0);
