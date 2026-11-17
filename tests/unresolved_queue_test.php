<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\UnresolvedListingQueue as Q;

/** Unresolved listings queue (LS-3 J-1, decision D-5): suggestions are ranked, aliases are validated and proven safe, nothing is automatic. */
function ok_uq(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- pure part: suggestions, proposed alias text, alias text rules
$cat = [
    ['id' => 1, 'full_name' => 'NVIDIA GeForce RTX 5060', 'aliases' => []],
    ['id' => 2, 'full_name' => 'NVIDIA GeForce RTX 5060 Ti', 'aliases' => []],
    ['id' => 3, 'full_name' => 'AMD Radeon RX 6600 XT', 'aliases' => []],
    ['id' => 4, 'full_name' => 'RAM DDR5-5600 32GB', 'aliases' => []],
    ['id' => 5, 'full_name' => 'AMD Ryzen 5 5600', 'aliases' => []],
];
$s = Q::suggest('การ์ดจอ 5060 8GB สวยๆ', $cat);
ok_uq($s !== [] && $s[0]['product_id'] === 1, 'A plain 5060 title ranks the plain 5060 first, not the Ti');
$s = Q::suggest('RTX 5060 Ti 16GB', $cat);
ok_uq($s[0]['product_id'] === 2, 'A Ti title ranks the Ti first');
$s = Q::suggest('ซีพียู 5600 6core', $cat);
ok_uq(in_array(5, array_column($s, 'product_id'), true), 'The CPU is among the suggestions for a 5600 CPU title');
ok_uq(Q::suggest('มือสอง สภาพดี ไม่มีเลขรุ่น', $cat) === [], 'A title without a model number gets no suggestion');
ok_uq(count(Q::suggest('RTX 5060', $cat, 1)) === 1, 'The number of suggestions is capped');

ok_uq(Q::proposeAlias('ขาย rtx 5060 ti 16gb ประกันเหลือ') === 'rtx 5060 ti', 'The model phrase is proposed with its suffix');
ok_uq(Q::proposeAlias('Ryzen 5 5600 AM4') !== '' && str_contains(strtolower(Q::proposeAlias('Ryzen 5 5600 AM4')), '5600'), 'A Ryzen phrase is proposed');
ok_uq(Q::proposeAlias('ราคาถูกมาก') === '', 'No model phrase, nothing proposed');

// the variant must match: a Super title is never offered the plain model, a plain title never the Ti
$catSuper = [['id' => 7, 'full_name' => 'NVIDIA GeForce GTX 1650', 'aliases' => []]];
ok_uq(Q::suggest('Vga Gtx1650 Super มีแต่ตัว', $catSuper) === [], 'A Super title gets no suggestion when only the plain model exists');
ok_uq(Q::suggest('RTX 5060', [$cat[1]]) === [], 'A plain title is not offered the Ti');
ok_uq(Q::suffixSet('RTX 4070 Ti SUPER') === ['super', 'ti'] && Q::suffixSet('gtx1650s') === [] && Q::suffixSet('RX 6600 XT') === ['xt'], 'Suffix words are read from names and titles');

ok_uq(Q::aliasProblems('5600') === ['ALIAS_TOO_SHORT', 'ALIAS_ONLY_DIGITS'] || in_array('ALIAS_ONLY_DIGITS', Q::aliasProblems('56000'), true), 'A number alone is not an alias');
ok_uq(in_array('ALIAS_TOO_SHORT', Q::aliasProblems('rx 6'), true), 'A short alias is refused');
ok_uq(in_array('ALIAS_HAS_NO_MODEL_NUMBER', Q::aliasProblems('geforce card'), true), 'An alias without a model number is refused');
ok_uq(Q::aliasProblems('RTX 5060 8G') === [], 'A normal alias is accepted');

// ---- database part (rolled back)
global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(3));
    $user = (int)$db->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();
    if ($user === 0) {
        $db->prepare("INSERT INTO users (name,email,password_hash,role,is_active) VALUES ('UQ Reviewer',?, 'x', 'admin', 1)")->execute(["uq-$suffix@example.test"]);
        $user = (int)$db->lastInsertId();
    }
    $source = (int)$db->query("SELECT id FROM data_sources LIMIT 1")->fetchColumn();
    $catId = (int)$db->query("SELECT id FROM product_categories WHERE slug='gpu' LIMIT 1")->fetchColumn();
    $brand = (int)$db->query("SELECT id FROM brands WHERE slug='nvidia' LIMIT 1")->fetchColumn();
    $mkProduct = static function (string $slug, string $name) use ($db, $catId, $brand): int {
        $db->prepare("INSERT INTO products (category_id,brand_id,model_name,slug,full_name,is_active) VALUES (?,?,?,?,?,1)")->execute([$catId, $brand, $name, $slug, $name]);
        return (int)$db->lastInsertId();
    };
    $pid = $mkProduct("uq-gpu-$suffix", 'NVIDIA GeForce RTX 9871 UQ');
    $other = $mkProduct("uq-other-$suffix", 'NVIDIA GeForce RTX 9872 UQ');
    $mkRaw = static function (string $title, string $tag) use ($db, $source, $suffix): int {
        $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (?,?,?,?,NOW(),'processed')")
            ->execute([$source, hash('sha256', "uq-$tag-$suffix"), $title, '9000']);
        $raw = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes) VALUES (NULL,?, 'red','rejected','[\"UNRESOLVED_PRODUCT\"]')")->execute([$raw]);
        return $raw;
    };
    $q = new Q($db);
    ok_uq($q->actionsAvailable(), 'The decision table exists in the test database');
    $raw = $mkRaw('ขาย GeForce 9871-UQ มือสอง', 'a');
    $before = $q->count('open');
    ok_uq($before >= 1, 'The fixture listing is in the open queue');
    $row = null; foreach ($q->page(200, 0, 'open') as $r) { if ((int)$r['raw_id'] === $raw) { $row = $r; } }
    ok_uq($row !== null && $row['decided'] === null, 'The listing appears with no decision');

    // LS-4 K-1b: a listing the CURRENT catalog already matches (decided before the catalog grew) is "stale", not open
    $mkProduct("uq-stale-$suffix", 'NVIDIA GeForce RTX 9873 UQ');
    $staleRaw = $mkRaw('NVIDIA GeForce RTX 9873 UQ', 'stale');
    $inOpen = array_column($q->page(200, 0, 'open'), 'raw_id');
    $inStale = array_column($q->page(200, 0, 'stale'), 'raw_id');
    ok_uq(!in_array((string)$staleRaw, array_map('strval', $inOpen), true) && in_array((string)$staleRaw, array_map('strval', $inStale), true), 'A listing the catalog now matches is listed as stale, not open');
    ok_uq(in_array((string)$raw, array_map('strval', $inOpen), true) && !in_array((string)$raw, array_map('strval', $inStale), true), 'A listing still unmatched stays open');
    ok_uq($q->count('stale') === count($q->page(200, 0, 'stale')) && $q->count('open') === count($q->page(200, 0, 'open')), 'Counts equal the listed rows for open and stale');
    $before = $q->count('open');

    // refusals
    ok_uq($q->addAlias($raw, $pid, 'GeForce 9871-UQ Super', $user)['error'] === 'ALIAS_SUFFIX_DIFFERS_FROM_PRODUCT', 'A Super alias cannot point at a plain product');
    ok_uq($q->addAlias($raw, $pid, '9871', $user)['error'] === 'ALIAS_TOO_SHORT', 'A too-short alias is refused before anything is written');
    ok_uq($q->addAlias($raw, 99999999, 'GeForce 9871-UQ', $user)['error'] === 'PRODUCT_NOT_FOUND', 'An unknown product is refused');
    ok_uq($q->addAlias(99999999, $pid, 'GeForce 9871-UQ', $user)['error'] === 'LISTING_NOT_IN_QUEUE', 'A listing that is not in the queue is refused');
    ok_uq($q->addAlias($raw, $pid, 'GeForce 9872-UQ', $user)['error'] === 'ALIAS_DOES_NOT_RESOLVE_THIS_LISTING', 'An alias that does not resolve this listing is refused');
    $aliasCount = (int)$db->query('SELECT COUNT(*) FROM product_aliases')->fetchColumn();
    ok_uq((int)$db->query('SELECT COUNT(*) FROM unresolved_listing_reviews')->fetchColumn() === 0, 'Refusals wrote no decision');

    // a good alias: resolves the listing, steals nothing, writes alias + decision + audit
    $r = $q->addAlias($raw, $pid, 'GeForce 9871-UQ', $user, 'confirmed in test');
    ok_uq($r['ok'] && $r['alias_id'] > 0, 'A good alias is accepted: ' . json_encode($r));
    ok_uq((int)$db->query('SELECT COUNT(*) FROM product_aliases')->fetchColumn() === $aliasCount + 1, 'Exactly one alias was added');
    $d = $db->query("SELECT status, product_id, alias_id, reviewer_id FROM unresolved_listing_reviews WHERE raw_observation_id = $raw")->fetch();
    ok_uq($d && $d['status'] === 'alias_added' && (int)$d['product_id'] === $pid && (int)$d['alias_id'] === $r['alias_id'], 'The decision records the product and the alias');
    ok_uq((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='unresolved_alias_added' AND entity_id = {$r['alias_id']}")->fetchColumn() === 1, 'The alias creation is audited');
    ok_uq($q->count('open') === $before - 1 && $q->count('decided') >= 1, 'The listing moved from open to decided');
    ok_uq($q->addAlias($raw, $pid, 'GeForce 9871-UQ b', $user)['ok'] === false, 'A second decision for the same listing is refused');
    // the observation side stays untouched: no observation was created
    ok_uq((int)$db->query("SELECT COUNT(*) FROM price_observations WHERE raw_observation_id = $raw")->fetchColumn() === 0, 'No observation, snapshot or price was created');

    // steal guard: an alias that would pull another known listing onto a different product is refused
    $stolenRaw = $mkRaw('RTX 9872 UQ 12GB มือสอง', 'b');           // resolves to $other through its own name
    $thief = $mkRaw('ซื้อ RTX 9872 UQ ใหม่ล่าสุด', 'c');
    $cat = (new App\Models\CollectionPipeline())->catalog();
    ok_uq((new App\Services\ProductResolver\ProductResolver($cat))->resolve('RTX 9872 UQ 12GB มือสอง')->productId === $other, 'Fixture check: the listing to be stolen resolves to the other product today');
    $res = $q->addAlias($thief, $pid, 'RTX 9872 UQ', $user);
    ok_uq($res['ok'] === false && str_starts_with((string)$res['error'], 'ALIAS_WOULD_CHANGE_OTHER_LISTINGS'), 'An alias that would move another listing is refused: ' . json_encode($res));

    // dismiss
    $nr = $mkRaw('บอร์ดแปลกๆ ไม่ใช่รายการสินค้า', 'd');
    ok_uq($q->dismiss($nr, $user, 'noise')['ok'], 'A listing can be dismissed');
    ok_uq($q->dismiss($nr, $user)['error'] === 'ALREADY_DECIDED', 'A dismissed listing cannot be decided twice');
} finally {
    $db->rollBack();
}

echo "unresolved_queue_test: PASS\n";
