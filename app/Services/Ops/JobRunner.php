<?php
declare(strict_types=1);

namespace App\Services\Ops;

use PDO;
use Throwable;

/**
 * LS-89 BE-3: runs one named job so that it never runs twice at once, and writes down what happened (table job_runs, migration 038).
 * The stored summary holds counts only. A failure stores a short code; the details go to the server log.
 */
final class JobRunner
{
    public function __construct(private PDO $db)
    {
    }

    public function available(): bool
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_runs'")->fetchColumn() === 1;
    }

    /**
     * @param callable():array $work returns counts; it reports a failure by throwing (JobFailed carries a code and the counts)
     * @return array{status:string,summary:array,error:?string} status ok, failed or skipped (already running)
     */
    public function run(string $name, callable $work): array
    {
        $lock = 'mi_job_' . substr(hash('sha256', $name . '|' . (string)$this->db->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
        $get = $this->db->prepare('SELECT GET_LOCK(?, 0)');
        $get->execute([$lock]);
        if ((int)$get->fetchColumn() !== 1) {
            return ['status' => 'skipped', 'summary' => [], 'error' => 'ALREADY_RUNNING'];
        }
        $id = 0;
        try {
            if ($this->available()) {
                $this->db->prepare("INSERT INTO job_runs (job_name, status, started_at) VALUES (?, 'running', ?)")->execute([$name, date('Y-m-d H:i:s')]);
                $id = (int)$this->db->lastInsertId();
            }
            $result = ['status' => 'ok', 'summary' => [], 'error' => null];
            $outer = $this->db->inTransaction();
            try {
                $result['summary'] = $work();
            } catch (JobFailed $e) {
                $result = ['status' => 'failed', 'summary' => $e->summary, 'error' => $e->getMessage()];
            } catch (Throwable $e) {
                error_log('JOB_FAILED ' . $name . ' ' . get_class($e) . ': ' . $e->getMessage());
                $result = ['status' => 'failed', 'summary' => [], 'error' => 'UNEXPECTED_ERROR'];
            }
            // a transaction the job opened and left open is undone; the caller's own transaction is not touched
            if (!$outer && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($id > 0) {
                $this->db->prepare('UPDATE job_runs SET status = ?, finished_at = ?, summary = ?, error_code = ? WHERE id = ?')
                    ->execute([$result['status'], date('Y-m-d H:i:s'), json_encode($result['summary'], JSON_UNESCAPED_UNICODE), $result['error'], $id]);
            }
            return $result;
        } finally {
            $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
    }

    /** @return array<string,array> the newest run of every job, by name */
    public function latest(): array
    {
        if (!$this->available()) {
            return [];
        }
        $out = [];
        foreach ($this->db->query('SELECT j.* FROM job_runs j JOIN (SELECT job_name, MAX(id) id FROM job_runs GROUP BY job_name) m ON m.id = j.id ORDER BY j.job_name')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string)$row['job_name']] = $row;
        }
        return $out;
    }

    /** @return list<array> newest first */
    public function recent(int $limit = 30): array
    {
        if (!$this->available()) {
            return [];
        }
        return $this->db->query('SELECT * FROM job_runs ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)))->fetchAll(PDO::FETCH_ASSOC);
    }
}
