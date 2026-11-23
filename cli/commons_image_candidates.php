<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\CommonsImages;

/**
 * LS-70: looks for open-licence pictures of models that have none and downloads CANDIDATES into a review folder. Reads the database (SELECT only), talks to
 * commons.wikimedia.org, writes nothing to the database and nothing to public/. Approve rows in review.csv (approve = yes), then run cli/commons_image_apply.php.
 *
 * Usage: php cli/commons_image_candidates.php [--slug=x] [--category=cpu] [--limit=10] [--per=3] [--out=storage/import/commons_candidates] [--pause=3]
 * Default: models without a picture (no public/assets/products/<slug>.jpg and no product_images rows), --limit 10 models, --per 3 candidates each.
 */
global $config;
$o = getopt('', ['slug::', 'category::', 'limit::', 'per::', 'out::', 'pause::']);
$limit = max(1, min(200, (int) ($o['limit'] ?? 10))); $per = max(1, min(5, (int) ($o['per'] ?? 3))); $pause = max(2, (int) ($o['pause'] ?? 3)); // Commons answers HTTP 429 to a faster client
$out = rtrim(str_replace('\\', '/', (string) ($o['out'] ?? dirname(__DIR__) . '/storage/import/commons_candidates')), '/');
$db = Database::connection($config['db']);
$hasImages = \App\Services\Catalog\ImageImport::tableExists($db);
$sql = "SELECT p.id, p.slug, p.full_name, c.slug cat FROM products p JOIN product_categories c ON c.id = p.category_id WHERE p.is_active = 1";
$args = [];
if (!empty($o['slug'])) { $sql .= ' AND p.slug = :s'; $args['s'] = (string) $o['slug']; }
if (!empty($o['category'])) { $sql .= ' AND c.slug = :c'; $args['c'] = (string) $o['category']; }
$st = $db->prepare($sql . ' ORDER BY c.slug, p.full_name'); $st->execute($args);
$have = []; if ($hasImages) { foreach ($db->query('SELECT DISTINCT product_id FROM product_images')->fetchAll() as $r) { $have[(int) $r['product_id']] = true; } }
$todo = [];
foreach ($st->fetchAll() as $r) {
    if (empty($o['slug']) && (is_file(dirname(__DIR__) . '/public/assets/products/' . $r['slug'] . '.jpg') || isset($have[(int) $r['id']]))) { continue; }
    $todo[] = $r; if (count($todo) >= $limit) { break; }
}
if (!is_dir($out) && !mkdir($out, 0775, true)) { fwrite(STDERR, "cannot create $out\n"); exit(2); }
$csvPath = $out . '/review.csv'; $new = !is_file($csvPath);
$csv = fopen($csvPath, 'ab'); if ($new) { fwrite($csv, "\xEF\xBB\xBF"); fputcsv($csv, ['slug', 'file', 'approve', 'commons_title', 'page_url', 'author', 'licence', 'licence_url', 'width', 'height'], ',', '"', ''); }
$found = 0; $none = []; $stopped = false;
/** one request with a patient retry: on HTTP 429 wait a minute and try once more, then give up the whole run (never hammer the service) */
$patient = static function (callable $f) { for ($i = 0; ; $i++) { try { return $f(); } catch (RuntimeException $e) { if (!str_contains($e->getMessage(), '429') || $i >= 1) { throw $e; } fwrite(STDERR, "rate limited, waiting 60 s\n"); sleep(60); } } };
foreach ($todo as $p) {
    $n = 0;
    try {
        $cands = [];
        foreach (CommonsImages::queries((string) $p['full_name']) as $query) { // shorter texts find more; stop at the first text that yields a usable picture
            sleep($pause); $titles = $patient(static fn () => CommonsImages::search($query, 20));
            if ($titles === []) { continue; }
            sleep($pause); $cands = $patient(static fn () => CommonsImages::details($titles, (string) $p['full_name']));
            if ($cands !== []) { break; }
        }
        foreach (array_slice($cands, 0, $per) as $c) {
            $n++; $ext = $c['mime'] === 'image/png' ? 'png' : ($c['mime'] === 'image/webp' ? 'webp' : 'jpg');
            $dir = $out . '/' . $p['slug']; if (!is_dir($dir)) { mkdir($dir, 0775, true); }
            $file = $p['slug'] . '-c' . $n . '.' . $ext; sleep($pause);
            $patient(static fn () => CommonsImages::download($c['thumb'], $dir . '/' . $file));
            fputcsv($csv, [$p['slug'], $p['slug'] . '/' . $file, '', $c['title'], $c['page'], $c['author'], $c['licence'], $c['licence_url'], $c['width'], $c['height']], ',', '"', '');
            $found++;
        }
    } catch (Throwable $e) { fwrite(STDERR, $p['slug'] . ': ' . $e->getMessage() . "\n"); if (str_contains($e->getMessage(), '429')) { $stopped = true; } }
    if ($n === 0) { $none[] = $p['slug']; }
    echo str_pad((string) $p['slug'], 44) . $n . " candidate(s)\n";
    if ($stopped) { fwrite(STDERR, "Stopped: Commons keeps rate limiting. Run again later; finished models are not repeated if you pass --slug for the rest.\n"); break; }
}
fclose($csv);
// a plain contact sheet next to the CSV, to look at the pictures before approving (no script, opens from the folder)
$rowsOut = \App\Services\Catalog\GameFpsImport::readFile($csvPath); $h = '<!doctype html><meta charset="utf-8"><title>Commons candidates</title><h1>Commons candidates</h1><p>Mark approve = yes in review.csv for the pictures you want, then run cli/commons_image_apply.php.</p><table border="1" cellpadding="6">';
foreach ($rowsOut as $r) { $h .= '<tr><td><img src="' . htmlspecialchars(rawurlencode(dirname((string) $r['file'])) . '/' . rawurlencode(basename((string) $r['file']))) . '" width="260"></td><td><b>' . htmlspecialchars((string) $r['slug']) . '</b><br><a href="' . htmlspecialchars((string) $r['page_url']) . '">' . htmlspecialchars((string) $r['commons_title']) . '</a><br>' . htmlspecialchars((string) $r['licence']) . ' · ' . htmlspecialchars((string) $r['author']) . '</td></tr>'; }
file_put_contents($out . '/review.html', $h . '</table>');
echo json_encode(['models_checked' => count($todo), 'candidates' => $found, 'no_candidate' => $none, 'review_csv' => $csvPath], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
