echo "\nprovince x category (count, median asking price)\n";
$rows = $db->query('SELECT province, category, asking_price_thb p FROM listing_location_log WHERE asking_price_thb IS NOT NULL ORDER BY province, category, asking_price_thb')->fetchAll();
$g = [];
foreach ($rows as $r) { $g[$r['province'] . '|' . $r['category']][] = (int)$r['p']; }
foreach ($g as $k => $prices) {
    [$prov, $cat] = explode('|', $k); $n = count($prices);
    if ($n < 3) { continue; }
    printf("  %-22s %-8s %3d  median %s\n", $prov, $cat, $n, number_format($n % 2 ? $prices[intdiv($n, 2)] : ($prices[$n / 2 - 1] + $prices[$n / 2]) / 2));
}