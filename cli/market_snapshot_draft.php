<?php
declare(strict_types=1);

/**
 * LS-24 C3: prints a DRAFT article "the market today" from the stored public prices (read-only, writes nothing to the database). Paste the body into
 * /admin/articles as a draft and decide yourself whether to publish. Add --out=file to save it instead of printing.
 *
 *   php cli/market_snapshot_draft.php [--out=storage/import/market-today.txt] [--per-category=3]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Product;
use App\Services\Blog\MarketSnapshotDraft;

$out = null; $per = 3;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--out=')) { $out = substr($a, 6); } elseif (str_starts_with($a, '--per-category=')) { $per = max(1, min(10, (int)substr($a, 15))); }
}
$d = MarketSnapshotDraft::build((new Product())->search(), new DateTimeImmutable('now'), $per);
$text = "TITLE: {$d['title']}\nSLUG: {$d['slug']}\nEXCERPT: {$d['excerpt']}\n\n{$d['body']}\n";
if ($out !== null) { file_put_contents($out, $text); echo "wrote $out ({$d['priced']} priced models of {$d['models']})\n"; exit(0); }
echo $text;
exit(0);
