<?php
declare(strict_types=1);

/**
 * LS-32: merges the owner's hand-collected batch files (observations.json, observations_batchN.json) into one file for cli/import_market_evidence.php.
 * Read-only on the batch folder; writes only <folder>/merged_for_import.json (storage/import is git-ignored).
 *  - rows with a capture_method come from the batch files as they are
 *  - legacy rows without one are taken from observations_combined_provenance_candidate.json instead (same observations, source_item_id and capture_method added)
 *  - review_*, quarantine_*, pending_* and *_review.json files are never merged: a person decides about those rows first
 * Usage: php cli/intake_merge_owner_batches.php storage/import/facebook_20261122
 */

$dir = rtrim((string)($argv[1] ?? ''), '/\\');
if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php cli/intake_merge_owner_batches.php <folder>\n");
    exit(2);
}
$alt = $dir . '/observations_combined_provenance_candidate.json';
$records = static function (string $file): array {
    $json = json_decode((string)file_get_contents($file), true);
    if (!is_array($json) || !is_array($json['records'] ?? null)) {
        fwrite(STDERR, 'Not a {"records":[...]} file: ' . basename($file) . "\n");
        exit(1);
    }
    return $json['records'];
};
$key = static fn(array $r): string => (string)($r['source_listing_url'] ?? $r['source_url'] ?? '') . '|' . (string)($r['title'] ?? '') . '|' . (string)($r['displayed_price'] ?? '');

$merged = []; $legacy = []; $files = 0;
foreach (glob($dir . '/observations*.json') ?: [] as $file) {
    if (!preg_match('/^observations(_batch\d+)?\.json$/', basename($file))) { continue; }
    $files++;
    foreach ($records($file) as $row) {
        if (trim((string)($row['capture_method'] ?? '')) === '') { $legacy[$key($row)] = true; continue; }
        $merged[$key($row)] = $row;
    }
}
$replaced = 0;
if ($legacy !== []) {
    if (!is_file($alt)) { fwrite(STDERR, count($legacy) . " legacy rows lack capture_method and the provenance file is missing; nothing written\n"); exit(1); }
    foreach ($records($alt) as $row) {
        if (isset($legacy[$key($row)])) { $merged[$key($row)] = $row; $replaced++; }
    }
    if ($replaced !== count($legacy)) { fwrite(STDERR, 'Only ' . $replaced . ' of ' . count($legacy) . " legacy rows found in the provenance file; nothing written\n"); exit(1); }
}
$out = $dir . '/merged_for_import.json';
file_put_contents($out, json_encode(['records' => array_values($merged)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "batch files: $files; rows: " . count($merged) . " (legacy rows swapped for provenance versions: $replaced)\nwritten: $out\n";
