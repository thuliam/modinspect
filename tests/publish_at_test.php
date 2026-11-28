<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Blog\PublishAt as P;

/** LS-25: the optional publish time of an article, and that a future time keeps the article off the public blog until then. Test DB, rolled back. */
function ok_pa(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$now = new DateTimeImmutable('2026-11-20 10:00:00');
ok_pa(P::parse('2026-12-01T09:30', $now) === '2026-12-01 09:30:00' && P::parse('2026-12-01 09:30:15', $now) === '2026-12-01 09:30:00', 'datetime-local and plain forms are read, seconds dropped');
ok_pa(P::parse('', $now) === null && P::parse(null, $now) === null && P::parse('soon', $now) === null && P::parse('2026-02-31T10:00', $now) === null && P::parse('2026-13-01T10:00', $now) === null, 'Empty, text and impossible dates mean "use the usual rule"');
ok_pa(P::parse('2026-11-18T10:00', $now) === null && P::parse('2026-11-19T11:00', $now) === '2026-11-19 11:00:00', 'More than a day in the past is a typo and is ignored');
ok_pa(P::parse('2029-01-01T10:00', $now) === null && P::parse('2028-11-19T10:00', $now) === '2028-11-19 10:00:00', 'More than two years ahead is ignored');

global $config;
$db = Database::connection($config['db']);
ok_pa($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$src = (string)file_get_contents(ROOT_PATH . '/app/Controllers/AdminCatalogController.php');
ok_pa(str_contains($src, 'PublishAt::parse') && substr_count($src, ':pub_at0 IS NOT NULL THEN :pub_at1') === 2, 'Both the create and the update statement use the chosen time');
$db->beginTransaction();
try {
    $ins = $db->prepare("INSERT INTO articles (title,slug,body,status,published_at) VALUES (?,?,?, 'published', ?)");
    $s = bin2hex(random_bytes(3));
    $ins->execute(['Later ' . $s, 'later-' . $s, 'x', date('Y-m-d H:i:s', time() + 86400)]);
    $ins->execute(['Now ' . $s, 'now-' . $s, 'x', date('Y-m-d H:i:s', time() - 60)]);
    $blog = new App\Models\Blog($db);
    ok_pa($blog->findBySlug('later-' . $s) === null && $blog->findBySlug('now-' . $s) !== null, 'A published article with a future time is not public until it is due');
} finally { $db->rollBack(); }
echo "publish_at_test: PASS\n";
