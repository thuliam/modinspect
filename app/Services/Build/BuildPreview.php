<?php
declare(strict_types=1);

namespace App\Services\Build;

/**
 * LS-15: everything the Build page shows for a selection, from one place. Used by the live JSON endpoint (POST /build/preview) and by the saved
 * build page, so the price list, the totals, the six stats and the compatibility checks can never differ between them. No database access: the
 * caller hands in the catalog rows, the new-price references and the reference specs (BuildCatalog loads them once per request).
 *
 * Candidate status answers "does this part fit what is already chosen?" for every part of every category, using the SAME checks as the summary:
 *   ok       fits, or nothing chosen yet that it could clash with
 *   unknown  a figure is missing in the catalog (never shown as ok)
 *   warn     does not fit well but can work (a PSU that looks too small): shown with a warning, not hidden
 *   fail     cannot work together (wrong socket, wrong DDR, ...): hidden by the "compatible only" filter
 * The check "graphics card length" is unknown for every graphics card by design (it depends on the brand's model), so it is left out of candidate
 * status; it still appears in the summary checks.
 */
final class BuildPreview
{
    /** fixed order and Thai names of the eight categories */
    public const ROLES = ['cpu' => 'CPU', 'motherboard' => 'เมนบอร์ด', 'gpu' => 'การ์ดจอ', 'ram' => 'RAM', 'storage' => 'ที่เก็บข้อมูล', 'psu' => 'พาวเวอร์ซัพพลาย', 'case' => 'เคส', 'cooling' => 'ชุดระบายความร้อน'];

    /**
     * @param list<array> $products Product::search rows
     * @param array<string,int> $selection role => product id (anything not matching a product of that role is ignored)
     * @param array<int,array> $newRefs latest new-price reference per product id
     * @param array<int,array<string,string>> $specs reference specs per product id
     * @return array<string,mixed>
     */
    public function run(array $products, array $selection, array $newRefs, array $specs, float $budget, bool $withCandidates = true): array
    {
        $byId = []; foreach ($products as $p) { $byId[(int)$p['id']] = $p; }
        $chosen = [];   // role => product row
        foreach (self::ROLES as $role => $_label) {
            $id = (int)($selection[$role] ?? 0);
            if ($id > 0 && isset($byId[$id]) && strtolower((string)$byId[$id]['category_name']) === $role) { $chosen[$role] = $byId[$id]; }
        }
        $parts = $this->parts($chosen, $newRefs, $specs);

        $items = []; $prices = [];
        foreach (self::ROLES as $role => $label) {
            if (!isset($chosen[$role])) { $items[] = ['role' => $role, 'role_label' => $label, 'chosen' => false]; continue; }
            $p = $chosen[$role]; $pr = BuildPricing::for($p, $newRefs[(int)$p['id']] ?? null); $prices[] = $pr;
            $items[] = ['role' => $role, 'role_label' => $label, 'chosen' => true, 'id' => (int)$p['id'], 'name' => (string)$p['full_name'], 'slug' => (string)$p['slug'], 'brand' => (string)($p['brand_name'] ?? ''),
                'category_name' => (string)$p['category_name'], 'spec_summary' => (string)($p['spec_summary'] ?? ''), 'basis' => $pr['basis'], 'basis_label' => $pr['label'],
                'low' => $pr['low'], 'expected' => $pr['expected'], 'high' => $pr['high'], 'source_url' => $pr['source_url'], 'checked_on' => $pr['checked_on'],
                'confidence' => $pr['basis'] === 'used' ? (float)$p['confidence_score'] : 0.0, 'product' => $p];
        }
        $totals = BuildPricing::totals($prices);
        $checks = BuildCompatibility::check($parts);
        $out = [
            'items' => $items, 'chosen_count' => count($chosen), 'totals' => $totals, 'totals_note' => BuildPricing::totalsNote($totals),
            'budget' => $budget, 'remaining' => $budget > 0 ? $budget - $totals['expected'] : null,
            'stats' => $chosen ? BuildStats::axes($parts, $this->reference($products, $newRefs, $specs)) : [],
            'checks' => $checks, 'blocked' => BuildCompatibility::blocked($checks), 'psu_need' => BuildCompatibility::psuNeed($parts),
            'candidates' => [],
        ];
        if ($withCandidates && $chosen) { $out['candidates'] = $this->candidates($products, $chosen, $newRefs, $specs); }
        return $out;
    }

    /** @return array<string,array<int,array{status:string,reason:string}>> role => product id => status; only parts that are not simply "ok" are listed */
    private function candidates(array $products, array $chosen, array $newRefs, array $specs): array
    {
        $out = [];
        foreach ($products as $p) {
            $role = strtolower((string)$p['category_name']);
            if (!isset(self::ROLES[$role])) { continue; }
            if (isset($chosen[$role]) && (int)$chosen[$role]['id'] === (int)$p['id']) { continue; }   // the chosen part is not a candidate against itself
            $trial = $chosen; $trial[$role] = $p;
            $status = 'ok'; $reason = '';
            foreach (BuildCompatibility::check($this->parts($trial, $newRefs, $specs)) as $c) {
                if (!in_array($role, $c['roles'], true)) { continue; }
                if ($c['status'] === 'ok' || ($c['status'] === 'unknown' && $c['key'] === 'gpu_length')) { continue; }
                $level = $c['status'] === 'fail' ? ($c['blocking'] ? 'fail' : 'warn') : 'unknown';
                $rank = ['ok' => 0, 'unknown' => 1, 'warn' => 2, 'fail' => 3];
                if ($rank[$level] > $rank[$status]) { $status = $level; $reason = $c['text']; }
            }
            if ($status !== 'ok') { $out[$role][(int)$p['id']] = ['status' => $status, 'reason' => $reason]; }
        }
        return $out;
    }

    /** @param array<string,array> $byRole @return array<string,array{product:array,specs:array<string,string>,price:float}> */
    private function parts(array $byRole, array $newRefs, array $specs): array
    {
        $out = [];
        foreach ($byRole as $role => $p) {
            $out[$role] = ['product' => $p, 'specs' => $specs[(int)$p['id']] ?? [], 'price' => BuildPricing::for($p, $newRefs[(int)$p['id']] ?? null)['expected']];
        }
        return $out;
    }

    private function reference(array $products, array $newRefs, array $specs): array
    {
        $entries = [];
        foreach ($products as $p) {
            $role = strtolower((string)($p['category_slug'] ?? $p['category_name']));
            if ($role !== 'cpu' && $role !== 'gpu') { continue; }
            $entries[] = ['role' => $role, 'product' => $p, 'specs' => $specs[(int)$p['id']] ?? [], 'price' => BuildPricing::for($p, $newRefs[(int)$p['id']] ?? null)['expected']];
        }
        return BuildStats::reference($entries);
    }
}
