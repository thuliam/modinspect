<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

global $config;
$db = Database::connection($config['db']);

$checks = [
    'product_alias_orphans' => "SELECT COUNT(*) FROM product_aliases a LEFT JOIN products p ON p.id=a.product_id WHERE p.id IS NULL",
    'active_users_missing_identity' => "SELECT COUNT(*) FROM users WHERE is_active=1 AND (email IS NULL OR email='' OR name IS NULL OR name='' OR password_hash IS NULL OR password_hash='')",
    'users_invalid_role' => "SELECT COUNT(*) FROM users WHERE role NOT IN ('user','reviewer','operator','admin')",
    'users_duplicate_email' => "SELECT COUNT(*) FROM (SELECT email FROM users GROUP BY email HAVING COUNT(*)>1) duplicate_email",
    'collector_jobs_missing_source' => "SELECT COUNT(*) FROM collector_jobs j LEFT JOIN data_sources s ON s.id=j.source_id WHERE s.id IS NULL",
    'collector_jobs_missing_product' => "SELECT COUNT(*) FROM collector_jobs j LEFT JOIN products p ON p.id=j.product_id WHERE j.product_id IS NOT NULL AND p.id IS NULL",
    'raw_observations_missing_source' => "SELECT COUNT(*) FROM raw_price_observations r LEFT JOIN data_sources s ON s.id=r.source_id WHERE s.id IS NULL",
    'raw_observations_missing_job' => "SELECT COUNT(*) FROM raw_price_observations r LEFT JOIN collector_jobs j ON j.id=r.collector_job_id WHERE r.collector_job_id IS NOT NULL AND j.id IS NULL",
    'evidence_missing_raw' => "SELECT COUNT(*) FROM market_evidence e LEFT JOIN raw_price_observations r ON r.id=e.raw_observation_id WHERE r.id IS NULL",
    'evidence_missing_source' => "SELECT COUNT(*) FROM market_evidence e LEFT JOIN data_sources s ON s.id=e.source_id WHERE s.id IS NULL",
    'extractions_missing_raw' => "SELECT COUNT(*) FROM extraction_runs x LEFT JOIN raw_price_observations r ON r.id=x.raw_observation_id WHERE r.id IS NULL",
    'extractions_missing_evidence' => "SELECT COUNT(*) FROM extraction_runs x LEFT JOIN market_evidence e ON e.id=x.evidence_id WHERE x.evidence_id IS NOT NULL AND e.id IS NULL",
    'reviews_missing_raw' => "SELECT COUNT(*) FROM observation_review_decisions d LEFT JOIN raw_price_observations r ON r.id=d.raw_observation_id WHERE d.raw_observation_id IS NOT NULL AND r.id IS NULL",
    'reviews_missing_observation' => "SELECT COUNT(*) FROM observation_review_decisions d LEFT JOIN price_observations o ON o.id=d.price_observation_id WHERE d.price_observation_id IS NOT NULL AND o.id IS NULL",
    'observations_missing_product' => "SELECT COUNT(*) FROM price_observations o LEFT JOIN products p ON p.id=o.product_id WHERE p.id IS NULL",
    'observations_missing_raw' => "SELECT COUNT(*) FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE o.raw_observation_id IS NOT NULL AND r.id IS NULL",
    'corrections_missing_observation' => "SELECT COUNT(*) FROM review_corrections c LEFT JOIN price_observations o ON o.id=c.price_observation_id WHERE o.id IS NULL",
    'corrections_missing_review' => "SELECT COUNT(*) FROM review_corrections c LEFT JOIN observation_review_decisions d ON d.id=c.review_decision_id WHERE c.review_decision_id IS NOT NULL AND d.id IS NULL",
    'accepted_observations_invalid_value' => "SELECT COUNT(*) FROM price_observations WHERE verified_status='approved' AND (price_value IS NULL OR price_value<=0 OR price_value>10000000 OR (price_type='asking' AND asking_price IS NULL))",
    'accepted_observations_missing_review_lineage' => "SELECT COUNT(*) FROM price_observations o LEFT JOIN observation_review_decisions d ON d.price_observation_id=o.id AND d.decision='approved' WHERE o.verified_status='approved' AND d.id IS NULL",
    'duplicate_accepted_raw_lineage' => "SELECT COUNT(*) FROM (SELECT raw_observation_id FROM price_observations WHERE verified_status='approved' AND raw_observation_id IS NOT NULL GROUP BY raw_observation_id HAVING COUNT(*)>1) duplicate_raw",
    'snapshots_missing_product' => "SELECT COUNT(*) FROM price_indices i LEFT JOIN products p ON p.id=i.product_id WHERE p.id IS NULL",
    'snapshot_members_missing_snapshot' => "SELECT COUNT(*) FROM price_snapshot_observations m LEFT JOIN price_indices i ON i.id=m.snapshot_id WHERE i.id IS NULL",
    'snapshot_members_missing_observation' => "SELECT COUNT(*) FROM price_snapshot_observations m LEFT JOIN price_observations o ON o.id=m.observation_id WHERE o.id IS NULL",
    'recorded_snapshots_without_members' => "SELECT COUNT(*) FROM (SELECT i.id FROM price_indices i LEFT JOIN price_snapshot_observations m ON m.snapshot_id=i.id WHERE i.provenance_status='recorded' GROUP BY i.id HAVING COUNT(m.id)=0) missing_members",
    'approved_mock_observation_risk' => "SELECT COUNT(*) FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN data_sources s ON s.id=r.source_id WHERE o.verified_status='approved' AND (LOWER(COALESCE(s.source_key,'')) LIKE '%mock%' OR LOWER(COALESCE(s.name,'')) LIKE '%mock%' OR LOWER(COALESCE(s.domain,'')) LIKE 'mock.%')",
    'approved_category_invalid_snapshot_risk' => "SELECT COUNT(*) FROM price_observations WHERE verified_status='approved' AND listing_type='single_item' AND price_type='asking' AND COALESCE(quality_flags,'') REGEXP 'WRONG_PRODUCT|MOBILE_GPU|WANTED|WHOLE_PC|BUNDLE|DEPOSIT|DEFECTIVE'",
    'running_jobs_with_completed_at' => "SELECT COUNT(*) FROM collector_jobs WHERE status='running' AND completed_at IS NOT NULL",
    'completed_jobs_without_completed_at' => "SELECT COUNT(*) FROM collector_jobs WHERE status='completed' AND completed_at IS NULL",
];

$results = [];
$problemCount = 0;
foreach ($checks as $name => $sql) {
    $count = (int)$db->query($sql)->fetchColumn();
    $results[$name] = $count;
    $problemCount += $count;
}

$report = [
    'ok' => $problemCount === 0,
    'problem_count' => $problemCount,
    'checks' => $results,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($problemCount === 0 ? 0 : 1);
