<?php
declare(strict_types=1);

namespace App\Services\Intake;

use DateTimeImmutable;

/**
 * LS-23 B2: "is it complete?" for the rows that passed IntakeValidator, measured against the criteria in database/contracts/intake-v1.json. Pure.
 * Output carries catalog names and counts only, never a collector's title, price or link.
 *
 * context:
 *   now         DateTimeImmutable
 *   resolve     callable(string $title): ?string   catalog full_name for a listing title (A), null when it cannot be matched
 *   products    array<normalizedName, category>
 *   names       array<normalizedName, fullName>    display names of the catalog
 *   b_targets   list<string>                       full names that still need a new-price reference
 */
final class IntakeCoverage
{
    /** @param array<string,array> $results dataset => IntakeValidator::validateFile() result @return array<string,array<string,mixed>> */
    public static function evaluate(array $results, array $context = []): array
    {
        $c = IntakeValidator::contract();
        $out = [];
        if (isset($results['A'])) { $out['A'] = self::usedListings($results['A']['valid'], $results['A_extra']['valid'] ?? [], $c['datasets']['A']['criteria']['per_product'], $context); }
        if (isset($results['B'])) { $out['B'] = self::newPrices($results['B']['valid'], $context); }
        if (isset($results['C'])) { $out['C'] = self::specs($results['C']['valid'], $c['spec_keys']['required_per_category'], $context); }
        if (isset($results['D_aliases'])) { $out['D_aliases'] = self::aliases($results['D_aliases']['valid'], (int)$c['datasets']['D_aliases']['criteria']['per_product']['min_aliases']); }
        if (isset($results['E'])) { $out['E'] = self::checks($results['E']['valid'], $c['datasets']['E']['criteria']['per_category']); }
        return $out;
    }

    private static function usedListings(array $rows, array $extra, array $crit, array $ctx): array
    {
        $now = $ctx['now'] ?? new DateTimeImmutable('now');
        $guess = [];
        foreach ($extra as $x) { if (($x['product_name_guess'] ?? '') !== '') { $guess[$x['source_item_id']] = $x['product_name_guess']; } }
        $per = []; $unresolved = 0; $outOfWindow = 0;
        foreach ($rows as $r) {
            $when = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $r['observed_at']);
            if ($when && $when < $now->modify('-' . (int)$crit['window_days'] . ' days')) { $outOfWindow++; continue; }
            $name = isset($ctx['resolve']) ? $ctx['resolve']($r['listing_title']) : null;
            if ($name === null && isset($guess[$r['source_item_id']])) { $name = $ctx['names'][IntakeValidator::norm($guess[$r['source_item_id']])] ?? null; }
            if ($name === null) { $unresolved++; continue; }
            $host = strtolower((string)(parse_url($r['source_listing_url'], PHP_URL_HOST) ?? ''));
            $host = preg_replace('/^(www|m|mbasic|web)\./', '', $host);
            $per[$name]['listings'] = ($per[$name]['listings'] ?? 0) + 1;
            $per[$name]['sources'][$host] = true;
        }
        $ok = []; $short = [];
        foreach ($per as $name => $p) {
            $row = ['name' => $name, 'listings' => $p['listings'], 'sources' => count($p['sources'])];
            if ($row['listings'] >= (int)$crit['min_listings'] && $row['sources'] >= (int)$crit['min_sources']) { $ok[] = $row; } else { $short[] = $row; }
        }
        return ['products_meeting_criteria' => $ok, 'products_below_criteria' => $short, 'unresolved_rows' => $unresolved, 'rows_outside_window' => $outOfWindow, 'criteria' => $crit];
    }

    private static function newPrices(array $rows, array $ctx): array
    {
        $have = [];
        foreach ($rows as $r) { $have[IntakeValidator::norm($r['product_name'])] = ($have[IntakeValidator::norm($r['product_name'])] ?? 0) + 1; }
        $targets = $ctx['b_targets'] ?? null;
        if ($targets === null) { return ['models_with_rows' => count($have)]; }
        $missing = []; $covered = 0; $two = 0;
        foreach ($targets as $t) {
            $n = $have[IntakeValidator::norm($t)] ?? 0;
            if ($n === 0) { $missing[] = $t; } else { $covered++; if ($n >= 2) { $two++; } }
        }
        return ['targets' => count($targets), 'covered' => $covered, 'with_two_or_more_shops' => $two, 'missing' => $missing];
    }

    private static function specs(array $rows, array $required, array $ctx): array
    {
        $by = [];
        foreach ($rows as $r) { $by[IntakeValidator::norm($r['product_name'])][$r['spec_key']] = true; }
        $complete = []; $incomplete = []; $unknown = 0;
        foreach ($by as $key => $keys) {
            $cat = $ctx['products'][$key] ?? null;
            if ($cat === null || !isset($required[$cat])) { $unknown++; continue; }
            $missing = array_values(array_diff($required[$cat], array_keys($keys)));
            $name = $ctx['names'][$key] ?? $key;
            if ($missing === []) { $complete[] = $name; } else { $incomplete[] = ['name' => $name, 'category' => $cat, 'missing_required' => $missing]; }
        }
        return ['models_complete' => $complete, 'models_incomplete' => $incomplete, 'models_without_known_category' => $unknown];
    }

    private static function aliases(array $rows, int $min): array
    {
        $per = [];
        foreach ($rows as $r) { $per[$r['product_name']] = ($per[$r['product_name']] ?? 0) + 1; }
        $ok = array_keys(array_filter($per, static fn(int $n): bool => $n >= $min));
        $short = []; foreach ($per as $name => $n) { if ($n < $min) { $short[] = ['name' => $name, 'aliases' => $n]; } }
        return ['models_meeting_criteria' => $ok, 'models_below_criteria' => $short, 'min_aliases' => $min];
    }

    private static function checks(array $rows, array $crit): array
    {
        $per = [];
        foreach ($rows as $r) { $per[$r['category']]['n'] = ($per[$r['category']]['n'] ?? 0) + 1; $per[$r['category']]['must'] = ($per[$r['category']]['must'] ?? 0) + ($r['severity'] === 'must_check' ? 1 : 0); }
        $ok = []; $short = [];
        foreach ($per as $cat => $p) { if ($p['n'] >= (int)$crit['min_checks'] && $p['must'] >= (int)$crit['min_must_check']) { $ok[] = $cat; } else { $short[] = ['category' => $cat, 'checks' => $p['n'], 'must_check' => $p['must']]; } }
        return ['categories_meeting_criteria' => $ok, 'categories_below_criteria' => $short, 'criteria' => $crit];
    }
}
