<?php
declare(strict_types=1);

namespace App\Services\Intake;

/**
 * LS-23 B3: joins the optional facts file (A_used_listings_extra.csv) to the listing file (A_used_listings.csv) by source_item_id and renames them to the
 * columns the offline importer already understands (ListingAttributes + has_box/has_receipt), so one import file carries everything. Pure.
 *   warranty_remaining_months -> warranty_months (+ under_warranty)     usage_hint_months -> product_age_months     province, has_box, has_receipt as they are
 *   flag defective -> is_defective=1 (kept; the price engine already ignores defective rows)    flag wanted | trade | bundle -> the row is left out (not a plain asking price)
 *   flag repost | negotiable -> kept (the pipeline's own repost handling and the review queue decide)
 * product_name_guess is a hint for the coverage report only and is not imported. The extra facts are NOT part of the frozen owner-transcription snapshot.
 */
final class IntakeExtras
{
    public const COLUMNS = ['capture_method', 'source_listing_url', 'source_item_id', 'listing_title', 'asking_price', 'observed_at', 'condition_hint',
        'warranty_months', 'under_warranty', 'product_age_months', 'province', 'is_defective', 'has_box', 'has_receipt'];
    public const LEFT_OUT = ['wanted', 'trade', 'bundle'];

    /**
     * @param list<array<string,string>> $listings valid rows of dataset A
     * @param list<array<string,string>> $extras   valid rows of dataset A_extra
     * @return array{records:list<array<string,string>>,left_out:array<string,int>,extras_without_listing:int,extras_used:int}
     */
    public static function merge(array $listings, array $extras): array
    {
        $by = [];
        foreach ($extras as $x) { $by[$x['source_item_id']] = $x; }
        $ids = array_flip(array_column($listings, 'source_item_id'));
        $out = []; $left = []; $used = 0;
        foreach ($listings as $l) {
            $x = $by[$l['source_item_id']] ?? [];
            $flag = trim($x['flag'] ?? '');
            if (in_array($flag, self::LEFT_OUT, true)) { $left[$flag] = ($left[$flag] ?? 0) + 1; continue; }
            $rec = array_fill_keys(self::COLUMNS, '');
            foreach (['capture_method', 'source_listing_url', 'source_item_id', 'listing_title', 'asking_price', 'observed_at', 'condition_hint'] as $k) { $rec[$k] = $l[$k] ?? ''; }
            if ($x !== []) {
                $used++;
                $w = trim($x['warranty_remaining_months'] ?? '');
                if ($w !== '') { $rec['warranty_months'] = $w; $rec['under_warranty'] = (int)$w > 0 ? '1' : '0'; }
                $rec['product_age_months'] = trim($x['usage_hint_months'] ?? '');
                $rec['province'] = trim($x['province'] ?? '');
                $rec['has_box'] = trim($x['has_box'] ?? '');
                $rec['has_receipt'] = trim($x['has_receipt'] ?? '');
                if ($flag === 'defective') { $rec['is_defective'] = '1'; }
            }
            $out[] = $rec;
        }
        return ['records' => $out, 'left_out' => $left, 'extras_without_listing' => count(array_diff_key($by, $ids)), 'extras_used' => $used];
    }
}
