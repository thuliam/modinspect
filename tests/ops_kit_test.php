<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Ops\BackupRetention;
use App\Services\Ops\ProbeRunner;

/** LS-4 K-4: probe rules and backup retention, with an injected fetcher and a temp directory (no network, no database). */
function ok_o(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$good = static function (string $p): array {
    return match (true) {
        $p === '/health' => [200, '{"status":"ok","database":"ok"}', 0.01],
        $p === '/admin' => [302, '', 0.01],
        in_array($p, ['/.env', '/app/bootstrap.php', '/composer.json'], true) => [404, '', 0.01],
        default => [200, '<html>fine</html>', 0.05],
    };
};
$r = (new ProbeRunner($good))->run();
ok_o($r['ok'] && $r['checked'] === 12, 'A healthy site passes all 12 requests: ' . json_encode($r));

$cases = [
    'health down' => static fn(string $p) => $p === '/health' ? [503, '{"status":"unavailable"}', 0.1] : $good($p),
    'env exposed' => static fn(string $p) => $p === '/.env' ? [200, 'APP_ENV=x', 0.1] : $good($p),
    'admin open' => static fn(string $p) => $p === '/admin' ? [200, 'dashboard', 0.1] : $good($p),
    'php error' => static fn(string $p) => $p === '/price' ? [200, 'x PHP Warning: y', 0.1] : $good($p),
    'slow page' => static fn(string $p) => $p === '/' ? [200, 'ok', 9.0] : $good($p),
    'page 500' => static fn(string $p) => $p === '/methodology' ? [500, '', 0.1] : $good($p),
];
foreach ($cases as $name => $fetch) {
    $res = (new ProbeRunner($fetch))->run();
    ok_o(!$res['ok'] && count($res['failures']) === 1, "$name must produce exactly one failure: " . json_encode($res['failures']));
}

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mi-ops-' . bin2hex(random_bytes(4));
mkdir($dir);
foreach ([1, 2, 3, 4, 5] as $i) { file_put_contents("$dir/b$i.sql", 'x'); touch("$dir/b$i.sql", 1700000000 + $i * 100); }
file_put_contents("$dir/notes.txt", 'keep me'); touch("$dir/notes.txt", 1600000000);
$p = BackupRetention::prune($dir, 2);
sort($p['deleted']);
ok_o($p['deleted'] === ['b1.sql', 'b2.sql', 'b3.sql'] && $p['kept'] === 2, 'Keeps the 2 newest .sql files: ' . json_encode($p));
ok_o(is_file("$dir/b5.sql") && is_file("$dir/b4.sql") && is_file("$dir/notes.txt"), 'Newest backups and a non-sql file survive');
$threw = false;
try { BackupRetention::prune($dir, 0); } catch (InvalidArgumentException) { $threw = true; }
ok_o($threw && is_file("$dir/b5.sql"), 'keep=0 is refused and deletes nothing');
array_map('unlink', glob("$dir/*") ?: []);
rmdir($dir);
echo "ops_kit_test: PASS\n";
