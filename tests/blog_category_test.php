<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Controllers\BlogController;
use App\Core\Database;
use App\Models\Blog;
use App\Services\Blog\ArticleCategory as C;

/** LS-55: article categories. Only known slugs are kept; the list filters by category and "general"; counts follow the search; the sidebar draws only when the column exists; a database test, rolled back. */
function ok_bc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_bc(C::normalize('GPU') === 'gpu' && C::normalize(' cpu ') === 'cpu' && C::normalize('hax') === null && C::normalize('general') === null && C::normalize(null) === null && C::normalize(['x']) === null, 'normalize keeps only the eight slugs');
ok_bc(C::filter('general') === 'general' && C::filter('ram') === 'ram' && C::filter('x') === null && C::filter('') === null, 'filter allows a slug, general or nothing');
ok_bc(C::label(null) === 'ทั่วไป' && C::label('gpu') === 'GPU', 'labels');

global $config;
$db = Database::connection($config['db']);
ok_bc($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
ok_bc(C::columnExists($db), 'The test database has articles.category (schema and migration 030)');
$tag = bin2hex(random_bytes(3));
$db->beginTransaction();
try {
    $ins = $db->prepare("INSERT INTO articles (title, slug, body, status, published_at, category) VALUES (:t, :s, 'x', 'published', NOW() - INTERVAL 1 DAY, :c)");
    foreach ([['a', 'gpu'], ['b', 'gpu'], ['c', 'cpu'], ['d', null]] as [$k, $c]) { $ins->execute(['t' => "T $k $tag", 's' => "bc-$k-$tag", 'c' => $c]); }
    $m = new Blog();
    ok_bc(count($m->page(1, 30, $tag)['rows']) === 4, 'No category shows all');
    ok_bc(count($m->page(1, 30, $tag, 'gpu')['rows']) === 2 && count($m->page(1, 30, $tag, 'cpu')['rows']) === 1 && count($m->page(1, 30, $tag, 'general')['rows']) === 1 && count($m->page(1, 30, $tag, 'ram')['rows']) === 0, 'The list filters by category and by general');
    $n = $m->categoryCounts($tag);
    ok_bc(($n['gpu'] ?? 0) === 2 && ($n['cpu'] ?? 0) === 1 && ($n['general'] ?? 0) === 1 && array_sum($n) === 4, 'Counts follow the search: ' . json_encode($n));
    $_SERVER['REQUEST_URI'] = '/blog'; $_GET = ['q' => $tag, 'cat' => 'gpu'];
    ob_start(); (new BlogController())->index(); $page = (string)ob_get_clean();
    ok_bc(str_contains($page, 'mi-blog-cats') && str_contains($page, 'aria-current="true"') && substr_count($page, 'mi-blog-tag') === 2 && !str_contains($page, "T c $tag"), 'The page shows the sidebar, marks the chosen category and lists only its articles');
    $_GET = ['cat' => '<script>'];
    ob_start(); (new BlogController())->index(); $page = (string)ob_get_clean();
    ok_bc(!str_contains($page, '<script>alert') && str_contains($page, 'mi-blog-cats'), 'An unknown category value is ignored');
} finally {
    $db->rollBack();
}
echo "blog_category_test: PASS\n";
