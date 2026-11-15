<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;
use PDO;

/**
 * Admin-only evidence view of one price snapshot: header, member counts, source concentration, evidence grade, replay result.
 * Rows carry date, price, grade and inclusion only: no source names, URLs, raw titles or external references.
 */
final class SnapshotEvidenceReport
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }

    public function forSnapshot(int $snapshotId): ?array
    {
        $h = $this->db->prepare('SELECT pi.id, pi.product_id, p.full_name, pi.last_calculated_at, pi.provenance_status, pi.formula_version, pi.confidence_method_version,
                pi.confidence_label, pi.confidence_score, pi.valid_sample_size, pi.q1, pi.median, pi.q3, pi.calculation_manifest
            FROM price_indices pi JOIN products p ON p.id = pi.product_id WHERE pi.id = :id');
        $h->execute(['id' => $snapshotId]);
        $s = $h->fetch();
        if (!$s) { return null; }

        $manifest = json_decode((string)($s['calculation_manifest'] ?? ''), true) ?: [];
        $m = $this->db->prepare("SELECT so.inclusion_status, so.exclusion_reason, so.calculation_price, so.snapshot_observed_at observed_at, so.snapshot_source_id source_id,
                CASE WHEN LOWER(COALESCE(ds.source_key,'')) LIKE 'offline_owner_text_%' THEN 'owner_text' ELSE 'other' END grade
            FROM price_snapshot_observations so LEFT JOIN data_sources ds ON ds.id = so.snapshot_source_id
            WHERE so.snapshot_id = :id ORDER BY so.snapshot_observed_at DESC, so.id DESC");
        $m->execute(['id' => $snapshotId]);
        $included = 0; $excluded = []; $grades = ['owner_text' => 0, 'other' => 0]; $perSource = []; $rows = [];
        foreach ($m->fetchAll() as $r) {
            $inc = $r['inclusion_status'] === 'included';
            if ($inc) {
                $included++; $grades[$r['grade']]++;
                $k = (string)($r['source_id'] ?? 'none'); $perSource[$k] = ($perSource[$k] ?? 0) + 1;
            } else {
                $reason = (string)($r['exclusion_reason'] ?: 'OTHER_DETERMINISTIC_RULE');
                $excluded[$reason] = ($excluded[$reason] ?? 0) + 1;
            }
            $rows[] = ['observed_at' => (string)$r['observed_at'], 'price' => (float)$r['calculation_price'], 'grade' => $r['grade'], 'included' => $inc, 'reason' => $inc ? null : (string)($r['exclusion_reason'] ?: 'OTHER_DETERMINISTIC_RULE')];
        }
        $dominant = $perSource ? max($perSource) / max(1, $included) : 0.0;
        return [
            'snapshot_id' => (int)$s['id'], 'product' => (string)$s['full_name'], 'calculated_at' => (string)$s['last_calculated_at'],
            'provenance_status' => (string)$s['provenance_status'], 'formula_version' => (string)$s['formula_version'], 'confidence_method_version' => (string)$s['confidence_method_version'],
            'confidence_label' => (string)$s['confidence_label'], 'confidence_score' => (float)$s['confidence_score'],
            'policy_caps' => array_values($manifest['confidence']['policy_caps'] ?? []),
            'quartiles' => ['q1' => (float)$s['q1'], 'median' => (float)$s['median'], 'q3' => (float)$s['q3']],
            'counts' => ['included' => $included, 'excluded_by_reason' => $excluded],
            'sources' => ['distinct' => count($perSource), 'dominant_share' => round($dominant, 4)],
            'grades' => $grades,
            'members' => $rows,
            'replay' => (new PriceSnapshotService())->reproduceSnapshot((int)$s['id'])['result'] ?? 'UNKNOWN',
        ];
    }
}
