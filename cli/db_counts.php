<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

global $config;
$db = Database::connection($config['db']);

$queries = [
    'products_by_category' => "SELECT c.slug, COUNT(*) count FROM products p JOIN product_categories c ON c.id=p.category_id GROUP BY c.slug ORDER BY c.slug",
    'cpu_gpu_alias_gaps' => "SELECT p.slug, p.full_name, COUNT(pa.id) alias_count FROM products p JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_aliases pa ON pa.product_id=p.id WHERE c.slug IN ('cpu','gpu') AND p.is_active=1 GROUP BY p.id HAVING alias_count < 5 ORDER BY c.slug,p.slug",
    'pipeline_counts' => "SELECT 'raw' name, COUNT(*) count FROM raw_price_observations UNION ALL SELECT 'evidence', COUNT(*) FROM market_evidence UNION ALL SELECT 'extractions', COUNT(*) FROM extraction_runs UNION ALL SELECT 'reviews', COUNT(*) FROM observation_review_decisions UNION ALL SELECT 'observations', COUNT(*) FROM price_observations UNION ALL SELECT 'accepted_observations', COUNT(*) FROM price_observations WHERE verified_status='approved' UNION ALL SELECT 'collection_runs', COUNT(*) FROM collection_runs UNION ALL SELECT 'coverage_jobs_queued', COUNT(*) FROM collector_jobs WHERE job_type='coverage_mock_collection' AND status='queued' UNION ALL SELECT 'coverage_jobs_running', COUNT(*) FROM collector_jobs WHERE job_type='coverage_mock_collection' AND status='running' UNION ALL SELECT 'coverage_jobs_completed', COUNT(*) FROM collector_jobs WHERE job_type='coverage_mock_collection' AND status='completed' UNION ALL SELECT 'coverage_jobs_failed', COUNT(*) FROM collector_jobs WHERE job_type='coverage_mock_collection' AND status='failed'",
];

$output = [];
foreach ($queries as $name => $sql) {
    $output[$name] = $db->query($sql)->fetchAll();
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
