<?php
declare(strict_types=1);

namespace App\Services\Research;

/**
 * Turns the sanitised research CSV into import records for `import_market_evidence --dataset=owner_text` (DL-013). This is the rule set that used to live
 * inside cli/owner_intake_prepare.php; the command line and the admin screen (LS-3 J-3) now share it, so there is exactly one definition of
 * "a row the owner could reasonably approve". Pure with respect to the database and the clock: it only reads the file it is given.
 *
 * Kept rows: a single item, no repost duplicate, not wanted/trade, not defective, no price anomaly, a numeric price, a known category and a valid date.
 * A GPU row whose only gap is the VRAM passes for models sold in one memory size (GpuVramFacts). Every record carries provenance_grade=OWNER_TEXT_NO_URL.
 */
final class OwnerIntakePreparer
{
    /** @return array<string,true> slugs of the spec-class products created by migration 014 (RAM / storage / PSU classes) */
    public static function knownSpecClasses(string $rootPath): array
    {
        $mig = (string)@file_get_contents($rootPath . '/database/migrations/014_catalog_spec_classes.sql');
        preg_match_all("/WHERE slug='([^']+)'\);/", $mig, $mm);
        return array_flip($mm[1]);
    }

    /**
     * @param array<string,true> $knownClasses only used when $spec is true
     * @return array{records:list<array<string,string|null>>,skipped:array<string,int>}
     */
    public static function prepare(string $csvPath, ?string $category = null, bool $spec = false, int $limit = 500, array $knownClasses = []): array
    {
        $fh = fopen($csvPath, 'rb');
        if ($fh === false) { return ['records' => [], 'skipped' => ['file_not_readable' => 1]]; }
        $header = fgetcsv($fh, 0, ',', '"', '');
        if ($header === false) { fclose($fh); return ['records' => [], 'skipped' => ['empty_file' => 1]]; }
        $header[0] = ltrim((string)$header[0], "\xEF\xBB\xBF");
        $records = []; $skipped = []; $seen = [];
        $skip = static function (string $why) use (&$skipped): void { $skipped[$why] = ($skipped[$why] ?? 0) + 1; };
        while (($cells = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($cells) < count($header)) { $cells = array_pad($cells, count($header), ''); }
            $r = array_combine($header, array_slice($cells, 0, count($header)));
            if ($category !== null && strtolower($r['category']) !== $category) { continue; }
            if (($r['listing_type'] ?? '') !== 'single_item') { $skip('not_single_item'); continue; }
            if (($r['bundle_total_line'] ?? '') === 'yes') { $skip('bundle'); continue; }
            if (($r['wanted_or_trade'] ?? '') === 'yes') { $skip('wanted_or_trade'); continue; }
            if (($r['defective_hint'] ?? '') === 'yes') { $skip('defective'); continue; }
            if (($r['price_anomaly'] ?? '') !== '') { $skip('price_anomaly'); continue; }
            if (!is_numeric($r['asking_price_thb'] ?? '') || (float)$r['asking_price_thb'] <= 0) { $skip('no_price'); continue; }
            $vramInferred = false;
            if (!in_array(($r['enrichment_status'] ?? ''), ['complete', 'partial', 'not_applicable', ''], true)) {
                // a GPU whose only gap is the VRAM is still unambiguous when that model was only ever sold with one memory size (public fact)
                if (!GpuVramFacts::acceptsMissingVram($r)) { $skip('enrichment_' . $r['enrichment_status']); continue; }
                $vramInferred = true;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($r['note_date'] ?? ''))) { $skip('no_date'); continue; }
            $classSlug = null;
            if ($spec) {
                $k = SpecClassifier::classify($r);
                if (!$k || !isset($knownClasses[$k['slug']])) { $skip('no_spec_class'); continue; }
                if (($r['bundle_total_line'] ?? '') === 'yes') { $skip('bundle'); continue; }
                $classSlug = $k['slug'];
            } elseif (in_array($r['category'], ['ram', 'storage', 'psu'], true)) { $skip('use_spec_mode'); continue; }
            $title = trim((string)$r['title']);
            // reposts: the same text and price must count once, otherwise one seller inflates the sample
            $dupKey = md5(mb_strtolower(preg_replace('/\s+/u', '', $title)) . '|' . (int)$r['asking_price_thb']);
            if (isset($seen[$dupKey])) { $skip('repost_duplicate'); continue; }
            $seen[$dupKey] = true;
            $records[] = [
                'source_label' => $spec ? 'owner_collected_facebook_text_spec' : 'owner_collected_facebook_text',
                'provenance_grade' => 'OWNER_TEXT_NO_URL',
                'source' => 'owner_collected_facebook_text',
                'source_item_id' => (string)$r['row_id'],
                'external_ref' => (string)$r['row_id'],
                'listing_title' => $title,
                'title' => $title,
                'listing_text' => $title,
                'asking_price' => (string)(int)$r['asking_price_thb'],
                'currency' => 'THB',
                'observed_at' => $r['note_date'] . ' 12:00:00',
                'catalog_slug' => $classSlug,
                'ingestion_note' => 'category=' . $r['category'] . '; model_key=' . $r['model_key'] . '; variant=' . $r['variant_key'] . '; enrichment=' . $r['enrichment_status'] . ($vramInferred ? '; vram_inferred=single_size_model' : ''),
            ];
            if (count($records) >= $limit) { break; }
        }
        fclose($fh);
        return ['records' => $records, 'skipped' => $skipped];
    }
}
