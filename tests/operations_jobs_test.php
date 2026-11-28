<?php
declare(strict_types=1);

/** LS-89 BE-3: migration ledger, job records, the daily routine (nothing is written without a backup) and the detailed health list. */
require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\DailyJobs;
use App\Services\Ops\JobFailed;
use App\Services\Ops\JobRunner;
use App\Services\Ops\MigrationLedger;
use App\Services\Ops\MigrationPlan;
use App\Services\Ops\SystemHealth;

function assert_oj(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    // ---- ledger ----
    $ledger = new MigrationLedger($db);
    assert_oj($ledger->available(), 'the ledger table exists on a fresh database');
    $db->exec('DELETE FROM schema_migrations');
    $sql = "UPDATE x SET y=1;\n";
    assert_oj($ledger->record('database/migrations/099_data_only.sql', $sql) === true, 'first record');
    assert_oj($ledger->record('099_data_only.sql', 'changed') === false, 'the first record is kept');
    assert_oj($ledger->all()['099_data_only.sql']['checksum'] === MigrationLedger::checksum("UPDATE x SET y=1;\r\n"), 'line endings do not change the checksum');
    $files = ['099_data_only.sql' => $sql, '098_table.sql' => 'CREATE TABLE ls89_nowhere (id INT);'];
    $without = MigrationPlan::build($files, [], 0);
    assert_oj($without['counts']['manual'] === 1 && $without['counts']['run'] === 1, 'without a ledger a data file is manual');
    $with = MigrationPlan::build($files, [], 0, ['099_data_only.sql' => true, '098_table.sql' => true]);
    assert_oj($with['counts']['skip'] === 1 && $with['counts']['run'] === 1 && $with['counts']['manual'] === 0, 'recorded data file is skipped; a missing table is still reported even when recorded');

    // ---- job runner ----
    $db->exec('DELETE FROM job_runs');
    $runner = new JobRunner($db);
    $ok = $runner->run('sample', static fn(): array => ['rows' => 3]);
    $bad = $runner->run('sample_bad', static function (): array { throw new JobFailed('SAMPLE_BROKE', ['rows' => 1]); });
    $boom = $runner->run('sample_boom', static function (): array { throw new RuntimeException('secret detail C:\\private\\path'); });
    assert_oj($ok['status'] === 'ok' && $bad['status'] === 'failed' && $bad['error'] === 'SAMPLE_BROKE' && $boom['error'] === 'UNEXPECTED_ERROR', 'job results');
    assert_oj($db->inTransaction(), 'the caller keeps its transaction');
    $stored = $runner->latest();
    assert_oj(json_decode((string)$stored['sample']['summary'], true) === ['rows' => 3] && $stored['sample']['finished_at'] !== null, 'summary stored');
    assert_oj(!str_contains(json_encode($stored), 'private'), 'no error text is stored');
    $other = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['port'], $config['db']['database']), $config['db']['username'], $config['db']['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $inner = null;
    $runner->run('locked', static function () use ($other, &$inner): array {
        $inner = (new JobRunner($other))->run('locked', static fn(): array => []);
        return [];
    });
    assert_oj($inner['status'] === 'skipped' && $inner['error'] === 'ALREADY_RUNNING', 'the same job cannot run twice at once');
    assert_oj($runner->run('locked', static fn(): array => [])['status'] === 'ok', 'the lock is released afterwards');

    // ---- daily routine ----
    $db->exec('DELETE FROM job_runs');
    $count = static fn(): int => (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn();
    $trendStamp = static fn(): string => json_encode($db->query('SELECT product_id,period_start,median FROM price_trend_points ORDER BY product_id,period_start')->fetchAll());
    // make at least one model eligible for a new price
    $db->exec("UPDATE price_observations SET verified_status='excluded' WHERE verified_status='approved'");
    $pid = (int)$db->query('SELECT id FROM products WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
    $db->exec("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,risk_level,is_active) VALUES ('jobs_sample_ls89','Jobs sample','jobs.example','research','manual','low',1)");
    $source = (int)$db->lastInsertId();
    foreach ([500, 600, 700] as $i => $price) {
        $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (?,?,?,?,NOW(),'processed')")->execute([$source, hash('sha256', "jobs-$i"), "Jobs $i", (string)$price]);
        $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at) VALUES (?,?,'asking',?,'THB','good','single_item',0,0,0,3,95,'approved',NOW())")->execute([(int)$db->lastInsertId(), $pid, $price]);
    }
    $goodBackup = static fn(): array => ['ok' => true, 'bytes' => 123, 'error' => null];
    $before = $count(); $trendBefore = $trendStamp();
    $report = (new DailyJobs($db, $goodBackup))->run(false);
    assert_oj(array_keys($report) === DailyJobs::JOBS, 'four jobs');
    assert_oj($report['backup']['status'] === 'ok' && $report['prices']['status'] === 'ok' && $report['trend']['status'] === 'ok', 'report mode runs: ' . json_encode($report));
    assert_oj($count() === $before && $trendStamp() === $trendBefore, 'report mode writes no price and no trend');

    $failing = (new DailyJobs($db, static fn(): array => ['ok' => false, 'bytes' => 0, 'error' => 'MYSQLDUMP_NOT_FOUND']))->run(true);
    assert_oj($failing['backup']['status'] === 'failed' && $failing['prices']['error'] === 'SKIPPED_NO_BACKUP' && $failing['trend']['error'] === 'SKIPPED_NO_BACKUP', 'no backup, no writes');
    assert_oj($count() === $before && $trendStamp() === $trendBefore, 'a failed backup leaves prices and trend alone');

    $applied = (new DailyJobs($db, $goodBackup))->run(true);
    assert_oj($applied['prices']['status'] === 'ok' && $applied['prices']['summary']['stored'] >= 1 && $count() > $before, 'apply stores the new price: ' . json_encode($applied['prices']));
    assert_oj((int)$db->query("SELECT COUNT(*) FROM price_trend_points WHERE product_id=$pid AND sample_size=3")->fetchColumn() === 1, 'apply rebuilds the trend');
    $again = (new DailyJobs($db, $goodBackup))->run(true);
    assert_oj($again['prices']['summary']['stored'] === 0, 'running again stores nothing new');

    // ---- health ----
    $health = new SystemHealth($db);
    $by = static function (array $checks): array { return array_column($checks, null, 'code'); };
    $h = $by($health->checks());
    assert_oj($h['database']['status'] === 'ok' && $h['migrations']['status'] === 'ok', 'database and migrations: ' . json_encode($h['migrations']));
    assert_oj($h['job:prices']['status'] === 'ok', 'a job that ran is ok');
    $db->exec("UPDATE job_runs SET started_at = DATE_SUB(started_at, INTERVAL 3 DAY)");
    assert_oj($by($health->checks())['job:prices']['status'] === 'warn', 'an old run warns');
    $db->exec("INSERT INTO job_runs (job_name,status,started_at,error_code) VALUES ('prices','failed',NOW(),'SAMPLE')");
    assert_oj($by($health->checks())['job:prices']['status'] === 'fail', 'a failed run fails');
    $db->exec("UPDATE price_observations SET verified_status='excluded' WHERE product_id=$pid");
    assert_oj($by($health->checks())['hidden_prices']['status'] === 'warn', 'a hidden price is reported');
    $empty = $by((new SystemHealth($db, sys_get_temp_dir() . '/ls89-no-such-root'))->checks());
    assert_oj($empty['folder:storage/logs']['status'] === 'warn' && $empty['backup_file']['status'] === 'warn', 'missing folders and backups warn');
    $text = json_encode($health->checks());
    foreach ([ROOT_PATH, str_replace('\\', '/', ROOT_PATH), (string)$config['db']['host']] as $private) { assert_oj(!str_contains($text, $private), 'no path or host in the health list'); }

    echo "operations_jobs_test: PASS\n";
} finally {
    $db->rollBack();
}
