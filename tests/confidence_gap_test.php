<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\ConfidenceGap;

function ok_cg(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
// the explanations follow the admin language (Thai by default); this test reads the English wording
$_SESSION['lang'] = 'en';

$g = ConfidenceGap::explain('low', ['OWNER_TEXT_ONLY_CAP', 'SINGLE_SOURCE_CAP'], 72.0, 13);
ok_cg(!$g['reaches_medium'] && count($g['blockers']) === 1 && str_contains($g['blockers'][0], 'different kind of source'), 'Owner-text-only is the single blocker when the raw score is already above 60');
ok_cg(count($g['notes']) === 1, 'Single-source cap is reported as a note, not a blocker');

$g = ConfidenceGap::explain('insufficient', ['INSUFFICIENT_SAMPLE'], 40.0, 2);
ok_cg(!$g['reaches_medium'] && count($g['blockers']) === 1 && str_contains($g['blockers'][0], '3 included'), 'Too few rows: one clear blocker, no duplicate raw-score line');

$g = ConfidenceGap::explain('low', ['TINY_SAMPLE_CAP'], 48.5, 4);
ok_cg(!$g['reaches_medium'] && str_contains($g['blockers'][0], 'Raw score 48.5'), 'Low raw score is a blocker');

ok_cg(ConfidenceGap::explain('medium', ['SINGLE_SOURCE_CAP'], 70.0, 9) === ['reaches_medium' => true, 'blockers' => [], 'notes' => []], 'Medium or high needs no explanation');

// LS-5 L-3: the human-recorded cap is explained, for medium as well as for low
$g = ConfidenceGap::explain('medium', ['HUMAN_RECORDED_EVIDENCE_CAP'], 75.0, 9);
ok_cg($g['reaches_medium'] && $g['blockers'] === [] && count($g['notes']) === 1 && str_contains($g['notes'][0], '79') && str_contains($g['notes'][0], 'machine capture'), 'Medium with the human-recorded cap carries one explanatory note');
$g = ConfidenceGap::explain('low', ['HUMAN_RECORDED_EVIDENCE_CAP', 'SINGLE_SOURCE_CAP'], 50.0, 9);
ok_cg(count($g['notes']) === 2, 'Low keeps the human-recorded note next to the other notes');

echo "confidence_gap_test: PASS\n";
