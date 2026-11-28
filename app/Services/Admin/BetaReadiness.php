<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;
use PDO;

/**
 * Read-only beta readiness panel (LS-3 J-9). The rows and their manual statuses come from `docs/BETA_READINESS_CHECKLIST.md` (the one place the
 * owner edits); a few rows that can be measured are overlaid with live values from this database and this environment. The verdict follows the
 * checklist's own rule: GO needs every MUST row met, anything else is NO-GO and the unmet MUST ids are listed. It decides nothing and changes nothing.
 */
final class BetaReadiness
{
    /** @return list<array{section:string,id:string,level:string,requirement:string,how:string,status:string,owner:bool,evidence:string,live:bool}> */
    public static function parse(string $markdown): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (!preg_match('/^\|\s*([A-F])-(\d+)\s*\|\s*(MUST|SHOULD)\s*\|(.*)\|\s*$/', $line, $m)) { continue; }
            $cells = array_map('trim', explode('|', $m[4]));
            if (count($cells) < 4) { continue; }
            [$requirement, $how, $statusRaw] = [$cells[0], $cells[1], $cells[2]];
            $evidence = implode(' | ', array_slice($cells, 3));
            $clean = trim(str_replace('**', '', $statusRaw));
            $status = str_contains($clean, 'NOT MET') ? 'not_met' : (str_starts_with($clean, 'MET') ? 'met' : (str_contains($clean, 'OWNER') ? 'owner' : 'unknown'));
            $rows[] = ['section' => $m[1], 'id' => $m[1] . '-' . $m[2], 'level' => $m[3], 'requirement' => $requirement, 'how' => $how, 'status' => $status,
                'owner' => str_contains($clean, 'OWNER'), 'evidence' => $evidence, 'live' => false];
        }
        return $rows;
    }

    /** @param list<array{id:string,level:string,status:string}> $rows @return array{verdict:string,unmet_must:list<string>,must:int,must_met:int,should:int,should_met:int} */
    public static function verdict(array $rows): array
    {
        $unmet = []; $must = 0; $mustMet = 0; $should = 0; $shouldMet = 0;
        foreach ($rows as $r) {
            if ($r['level'] === 'MUST') { $must++; if ($r['status'] === 'met') { $mustMet++; } else { $unmet[] = $r['id']; } }
            else { $should++; if ($r['status'] === 'met') { $shouldMet++; } }
        }
        return ['verdict' => ($must > 0 && $unmet === []) ? 'GO' : 'NO-GO', 'unmet_must' => $unmet, 'must' => $must, 'must_met' => $mustMet, 'should' => $should, 'should_met' => $shouldMet];
    }

    /**
     * Overlay measured values on parsed rows. $live keys: replay_total, replay_match, labels_present (bool), other_evidence (int), medium_or_better (int),
     * public_total (int), ladder_flags (int), ladder_checked (int), app_env (string), auth_override_present (bool).
     * @param list<array<string,mixed>> $rows @param array<string,mixed> $live @return list<array<string,mixed>>
     */
    public static function overlay(array $rows, array $live): array
    {
        $set = static function (array &$r, string $status, string $evidence): void { $r['status'] = $status; $r['evidence'] = $evidence; $r['live'] = true; };
        foreach ($rows as &$r) {
            switch ($r['id']) {
                case 'A-1':
                    $set($r, ($live['replay_total'] ?? 0) > 0 && $live['replay_match'] === $live['replay_total'] ? 'met' : 'not_met', 'live: ' . (int)$live['replay_match'] . ' of ' . (int)$live['replay_total'] . ' public prices replay to the same snapshot');
                    break;
                case 'A-3':
                    $set($r, !empty($live['labels_present']) && ($live['public_total'] ?? 0) > 0 ? 'met' : 'not_met', 'live: every one of the ' . (int)$live['public_total'] . ' public prices carries a confidence label');
                    break;
                case 'A-4':
                    $r['owner'] = true;
                    $set($r, ($live['other_evidence'] ?? 0) > 0 ? 'met' : 'not_met', 'live: ' . (int)$live['other_evidence'] . ' approved observations come from sources other than owner text');
                    break;
                case 'A-7':
                    $set($r, ($live['medium_or_better'] ?? 0) > 0 ? 'met' : 'not_met', 'live: ' . (int)$live['medium_or_better'] . ' of ' . (int)$live['public_total'] . ' public prices are medium or higher');
                    break;
                case 'A-8':
                    $set($r, ($live['ladder_flags'] ?? 1) === 0 ? 'met' : 'not_met', 'live: ' . (int)$live['ladder_flags'] . ' flags on ' . (int)$live['ladder_checked'] . ' prices checked (GPU, CPU, RAM)');
                    break;
                case 'C-4':
                    $ok = ($live['app_env'] ?? '') === 'production' && empty($live['auth_override_present']);
                    $set($r, $ok ? 'met' : 'not_met', 'live: this environment is APP_ENV=' . (($live['app_env'] ?? '') !== '' ? $live['app_env'] : '(unset)') . (!empty($live['auth_override_present']) ? ' with AUTH_* overrides set' : '') . '; the production host is a separate file to check');
                    break;
            }
        }
        unset($r);
        return $rows;
    }

    /** @return array{available:bool,rows:list<array<string,mixed>>,verdict:array<string,mixed>} */
    public function build(?PDO $db = null, ?string $docPath = null): array
    {
        global $config;
        $db = $db ?? Database::connection($config['db']);
        $path = $docPath ?? ((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3)) . '/docs/BETA_READINESS_CHECKLIST.md');
        $md = is_file($path) ? (string)file_get_contents($path) : '';
        $rows = self::parse($md);
        if ($rows === []) { return ['available' => false, 'rows' => [], 'verdict' => self::verdict([])]; }

        $health = (new DataHealthReport())->build();
        $ids = $db->query("SELECT pi.id, pi.confidence_label label FROM price_indices pi WHERE " . \App\Services\Pricing\PublicSnapshot::intactSql('pi') . " AND pi.id IN (SELECT MAX(id) FROM price_indices WHERE price_type='asking' AND product_variant_id IS NULL AND COALESCE(provenance_status,'legacy_unavailable') <> 'legacy_unavailable' GROUP BY product_id)
            AND pi.valid_sample_size >= 3 AND pi.median > 0")->fetchAll(PDO::FETCH_ASSOC);
        $svc = new PriceSnapshotService(); $match = 0;
        foreach ($ids as $i) { if ((($svc->reproduceSnapshot((int)$i['id']))['result'] ?? '') === 'MATCH') { $match++; } }
        $labels = true; foreach ($ids as $i) { if (trim((string)$i['label']) === '') { $labels = false; } }
        $live = ['replay_total' => count($ids), 'replay_match' => $match, 'labels_present' => $labels, 'public_total' => count($ids),
            'other_evidence' => (int)$health['grades']['other'], 'medium_or_better' => (int)$health['confidence']['medium'] + (int)$health['confidence']['high'],
            'ladder_flags' => (int)$health['ladder']['flags'], 'ladder_checked' => (int)$health['ladder']['checked'],
            'app_env' => strtolower((string)($_ENV['APP_ENV'] ?? '')), 'auth_override_present' => isset($_ENV['AUTH_IDLE_TIMEOUT_SECONDS']) || isset($_ENV['AUTH_ABSOLUTE_LIFETIME_SECONDS'])];
        $rows = self::overlay($rows, $live);
        return ['available' => true, 'rows' => $rows, 'verdict' => self::verdict($rows)];
    }
}
