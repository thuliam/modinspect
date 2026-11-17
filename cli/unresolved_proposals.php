<?php
declare(strict_types=1);

// LS-4 K-1: read-only alias proposals for the open unresolved listings. Prints product, alias, number of listings; never listing text.
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Admin\UnresolvedListingQueue;
use App\Services\Admin\UnresolvedProposalReport;

$queue = new UnresolvedListingQueue();
$rows = []; for ($o = 0; ; $o += 200) { $chunk = $queue->page(200, $o, "open"); if ($chunk === []) { break; } $rows = array_merge($rows, $chunk); }
$total = $queue->count('open');
$resolver = new \App\Services\ProductResolver\ProductResolver((new \App\Models\CollectionPipeline())->catalog());
$report = (new UnresolvedProposalReport($queue, $resolver))->build($rows);
echo "open unresolved: {$total} (scanned " . count($rows) . "); already matched by the current catalog (need a re-import, not an alias): {$report['stale']}\n";
printf("%-5s %-6s %-9s %-34s %s\n", 'safe', 'rows', 'newly', 'alias', 'product (id)');
foreach ($report['groups'] as $g) {
    printf("%-5s %-6d %-9d %-34s %s (%d) sample_id=%d\n", $g['safe'] ? 'yes' : 'NO', $g['listings'], $g['would_newly_resolve'], mb_substr($g['alias'], 0, 34), $g['product'], $g['product_id'], $g['sample_listing_id']);
}
echo "without a usable proposal: {$report['no_suggestion']}\n";
