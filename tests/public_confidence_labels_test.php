<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * LS-5 L-3: every reason or cap code the confidence service can emit has a Thai label on the public product page, so a visitor never sees a raw
 * code such as HUMAN_RECORDED_EVIDENCE_CAP. The codes are read from the service source, the labels from the view (the map lives there).
 */
function ok_pl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$service = (string)file_get_contents(__DIR__ . '/../app/Services/Pricing/SnapshotConfidenceService.php');
preg_match_all("/'([A-Z][A-Z_]{5,})'/", $service, $m);
$codes = array_values(array_unique($m[1]));
ok_pl(count($codes) >= 20 && in_array('HUMAN_RECORDED_EVIDENCE_CAP', $codes, true), 'The service source yields its reason and cap codes (' . count($codes) . ')');

$view = (string)file_get_contents(__DIR__ . '/../app/Views/price/show.php');
$a = strpos($view, "function mi_public_confidence_reason");
$b = strpos($view, '$hasMarketSnapshot');
ok_pl($a !== false && $b !== false && $b > $a, 'The label map is found in the view');
preg_match_all("/'([A-Z][A-Z_]{5,})'\s*=>\s*'([^']+)'/u", substr($view, $a, $b - $a), $lm);
$labels = array_combine($lm[1], $lm[2]);

$missing = [];
foreach ($codes as $c) { if (!isset($labels[$c]) || trim($labels[$c]) === '') { $missing[] = $c; } }
ok_pl($missing === [], 'These codes have no Thai label on the public page: ' . implode(', ', $missing));
foreach ($labels as $c => $t) { ok_pl(preg_match('/[\x{0E00}-\x{0E7F}]/u', $t) === 1, "$c label is Thai text"); }
echo 'public_confidence_labels_test: PASS (' . count($codes) . " codes labelled)\n";
