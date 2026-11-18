<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\CompareTable as C;

/**
 * F-3 real Compare (owner scope 2026-11-18): up to four models side by side on the same specification rows and the same price facts.
 * Rows are the union of the models' fact labels (a model without a fact shows a gap, never an invented value); a row is marked "differs" when the models
 * disagree. Price follows the single "has a market price" rule (median, valid sample, accepted observation); an unpriced model says so.
 * There is no winner, no score, no rank and no benchmark: nothing here judges which model is better. Pure (no database).
 */
function ok_ct(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$p = static fn(string $slug, string $cat, array $over = []): array => array_merge([
    'slug' => $slug, 'full_name' => strtoupper($slug), 'category_slug' => $cat, 'category_name' => strtoupper($cat),
    'median' => 5000, 'q1' => 4500, 'q3' => 5600, 'valid_sample_size' => 7, 'accepted_observation_count' => 7, 'confidence_label' => 'medium', 'confidence_score' => 62,
], $over);
$f = static fn(string $label, string $value, ?string $source = null, ?string $note = null): array => ['label' => $label, 'value' => $value, 'source' => $source, 'checked' => null, 'note' => $note];

$a = ['product' => $p('a', 'cpu'), 'facts' => [$f('จำนวนคอร์', '6'), $f('Socket', 'AM4', 'https://www.amd.com/a'), $f('L3 cache', '32 MB')]];
$b = ['product' => $p('b', 'cpu', ['median' => 9000, 'q1' => 8000, 'q3' => 9900]), 'facts' => [$f('Socket', 'AM4'), $f('จำนวนคอร์', '8'), $f('TDP', '105 W')]];
$t = C::build([$a, $b]);

ok_ct(count($t['columns']) === 2 && $t['columns'][0]['slug'] === 'a' && $t['columns'][1]['slug'] === 'b', 'Columns follow the order the models were given');
$labels = array_column($t['rows'], 'label');
ok_ct($labels === ['จำนวนคอร์', 'Socket', 'L3 cache', 'TDP'], 'Rows are the union of fact labels in order of first appearance: ' . json_encode($labels, JSON_UNESCAPED_UNICODE));
$by = array_column($t['rows'], null, 'label');
ok_ct($by['Socket']['cells'][0]['value'] === 'AM4' && $by['Socket']['cells'][1]['value'] === 'AM4' && $by['Socket']['differs'] === false, 'Equal values are not marked as different');
ok_ct($by['จำนวนคอร์']['differs'] === true && $by['จำนวนคอร์']['cells'][1]['value'] === '8', 'Different values are marked');
ok_ct($by['L3 cache']['cells'][1]['value'] === null && $by['L3 cache']['differs'] === true, 'A fact only one model has shows a gap (null) for the other and counts as a difference');
ok_ct($by['TDP']['cells'][0]['value'] === null && $by['TDP']['cells'][1]['value'] === '105 W', 'The gap can be in the first column too');
ok_ct($by['Socket']['cells'][0]['source'] === 'https://www.amd.com/a' && $by['Socket']['cells'][1]['source'] === null, 'A source link travels with its own cell only');
ok_ct($t['mixed_category'] === false, 'Same category is not mixed');

// price columns
ok_ct($t['columns'][0]['priced'] === true && $t['columns'][0]['median'] === 5000.0 && $t['columns'][0]['range'] === [4500.0, 5600.0] && $t['columns'][0]['samples'] === 7 && $t['columns'][0]['confidence'] === 'medium', 'A priced model carries range, median, samples and confidence: ' . json_encode($t['columns'][0]));
ok_ct($t['any_priced'] === true, 'any_priced is true when one model is priced');

// the single price rule: each condition alone makes a model unpriced
foreach ([['median' => null], ['median' => 0], ['valid_sample_size' => 0], ['accepted_observation_count' => 0]] as $over) {
    $u = C::build([['product' => $p('u', 'cpu', $over), 'facts' => []]]);
    ok_ct($u['columns'][0]['priced'] === false && $u['columns'][0]['median'] === null && $u['columns'][0]['range'] === null && $u['any_priced'] === false, 'Unpriced when ' . json_encode($over));
}
$mixedPrice = C::build([['product' => $p('u', 'cpu', ['median' => null]), 'facts' => []], $a]);
ok_ct($mixedPrice['columns'][0]['priced'] === false && $mixedPrice['columns'][1]['priced'] === true && $mixedPrice['any_priced'] === true, 'An unpriced model stays unpriced next to a priced one (nothing is borrowed)');

// mixed categories are flagged, never refused
$g = ['product' => $p('g', 'gpu'), 'facts' => [$f('VRAM', '8 GB')]];
ok_ct(C::build([$a, $g])['mixed_category'] === true, 'Different categories are flagged as mixed');

// at most four models, no duplicates
$many = [];
foreach (['a', 'b', 'c', 'd', 'e'] as $s) { $many[] = ['product' => $p($s, 'cpu'), 'facts' => []]; }
ok_ct(count(C::build($many)['columns']) === 4, 'At most four models are compared');
ok_ct(count(C::build([$a, $a, $b])['columns']) === 2, 'The same model twice is shown once');
$none = C::build([]);
ok_ct($none['columns'] === [] && $none['rows'] === [] && $none['any_priced'] === false && $none['mixed_category'] === false, 'Nothing selected gives an empty table');

// one model alone has nothing to differ from
$one = C::build([$a]);
ok_ct(count($one['rows']) === 3 && !in_array(true, array_column($one['rows'], 'differs'), true), 'A single model has no differences');

// no judgement anywhere: the output carries no winner, score, rank or benchmark
$json = strtolower(json_encode($t));
foreach (['winner', 'rank', 'benchmark', 'best', 'score'] as $w) { ok_ct(!str_contains(str_replace('confidence_score', '', $json), $w), "The comparison carries no '$w'"); }

// page wiring
$root = dirname(__DIR__);
$ctl = (string)file_get_contents($root . '/app/Controllers/ModuleController.php');
ok_ct(str_contains($ctl, 'CompareTable::build') && str_contains($ctl, 'referenceSpecs') && str_contains($ctl, 'ProductFacts::rows'), 'The controller builds the table from the same facts as the product page');
$view = (string)file_get_contents($root . '/app/Views/modules/compare.php');
ok_ct(str_contains($view, 'name="m[]"') && str_contains($view, 'htmlspecialchars') && str_contains($view, 'rel="noopener'), 'The picker sends m[]; values are escaped and source links open safely');
ok_ct(str_contains($view, 'ไม่ได้จัดอันดับ') || str_contains($view, 'ไม่จัดอันดับ'), 'The page says plainly that it does not rank');
echo "compare_table_test: PASS\n";
