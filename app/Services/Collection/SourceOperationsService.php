<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use PDO;

final class SourceOperationsService
{
    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function requeueFailedJob(int $jobId, bool $dryRun = false, ?int $actorId = null): array
    {
        $job = $this->findJob($jobId);
        if (!$job) {
            return ['job_id' => $jobId, 'status' => 'not_found', 'requeued' => false, 'errors' => ['job_not_found']];
        }
        if ($job['status'] !== 'failed') {
            return ['job_id' => $jobId, 'status' => 'rejected', 'requeued' => false, 'errors' => ['only_failed_jobs_can_be_requeued'], 'current_status' => $job['status']];
        }

        $after = [
            'status' => 'queued',
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
            'raw_count' => 0,
            'valid_count' => 0,
            'attempt_count' => (int)($job['attempt_count'] ?? 0),
        ];

        if ($dryRun) {
            return ['job_id' => $jobId, 'status' => 'dry_run', 'requeued' => false, 'before' => $this->jobSummary($job), 'after' => $after, 'errors' => []];
        }

        $stmt = $this->db->prepare(
            "UPDATE collector_jobs
             SET status='queued',run_id=NULL,started_at=NULL,completed_at=NULL,raw_count=0,valid_count=0,error_message=NULL,updated_at=NOW()
             WHERE id=:id AND status='failed'"
        );
        $stmt->execute(['id' => $jobId]);
        if ($stmt->rowCount() !== 1) {
            return ['job_id' => $jobId, 'status' => 'rejected', 'requeued' => false, 'errors' => ['job_status_changed']];
        }

        $this->audit('collection_job_requeued', 'collector_job', $jobId, $this->jobSummary($job), $after, $actorId);
        return ['job_id' => $jobId, 'status' => 'requeued', 'requeued' => true, 'before' => $this->jobSummary($job), 'after' => $after, 'errors' => []];
    }

    public function pauseSource(int $sourceId, string $reason, string $incidentType = 'manually_paused', bool $dryRun = false, ?int $actorId = null): array
    {
        $source = $this->findSource($sourceId);
        if (!$source) {
            return ['source_id' => $sourceId, 'status' => 'not_found', 'errors' => ['source_not_found']];
        }

        $after = ['is_active' => (int)$source['is_active'], 'is_paused' => 1, 'pause_reason' => $reason];
        if ($dryRun) {
            return ['source_id' => $sourceId, 'status' => 'dry_run', 'before' => $this->sourceSummary($source), 'after' => $after, 'errors' => []];
        }

        $stmt = $this->db->prepare("UPDATE data_sources SET is_paused=1,pause_reason=:reason,updated_at=NOW() WHERE id=:id");
        $stmt->execute(['reason' => $reason, 'id' => $sourceId]);
        $incident = $this->recordIncident($sourceId, $incidentType, $reason, 'paused', $actorId === null ? 'system' : 'user:' . $actorId, $actorId);
        $this->audit('source_paused', 'data_source', $sourceId, $this->sourceSummary($source), $after + ['incident_id' => $incident['incident_id']], $actorId);
        return ['source_id' => $sourceId, 'status' => 'paused', 'incident_id' => $incident['incident_id'], 'errors' => []];
    }

    public function disableSource(int $sourceId, string $reason, bool $dryRun = false, ?int $actorId = null): array
    {
        $source = $this->findSource($sourceId);
        if (!$source) {
            return ['source_id' => $sourceId, 'status' => 'not_found', 'errors' => ['source_not_found']];
        }

        $after = ['is_active' => 0, 'is_paused' => 1, 'disabled_reason' => $reason];
        if ($dryRun) {
            return ['source_id' => $sourceId, 'status' => 'dry_run', 'before' => $this->sourceSummary($source), 'after' => $after, 'errors' => []];
        }

        $stmt = $this->db->prepare("UPDATE data_sources SET is_active=0,is_paused=1,disabled_reason=:reason,updated_at=NOW() WHERE id=:id");
        $stmt->execute(['reason' => $reason, 'id' => $sourceId]);
        $incident = $this->recordIncident($sourceId, 'manually_disabled', $reason, 'disabled', $actorId === null ? 'system' : 'user:' . $actorId, $actorId);
        $this->audit('source_disabled', 'data_source', $sourceId, $this->sourceSummary($source), $after + ['incident_id' => $incident['incident_id']], $actorId);
        return ['source_id' => $sourceId, 'status' => 'disabled', 'incident_id' => $incident['incident_id'], 'errors' => []];
    }

    public function resumeSource(int $sourceId, string $reason = 'Manual resume', bool $dryRun = false, ?int $actorId = null): array
    {
        $source = $this->findSource($sourceId);
        if (!$source) {
            return ['source_id' => $sourceId, 'status' => 'not_found', 'errors' => ['source_not_found']];
        }

        $after = ['is_active' => (int)$source['is_active'], 'is_paused' => 0, 'pause_reason' => null];
        if ($dryRun) {
            return ['source_id' => $sourceId, 'status' => 'dry_run', 'before' => $this->sourceSummary($source), 'after' => $after, 'errors' => []];
        }

        $stmt = $this->db->prepare("UPDATE data_sources SET is_paused=0,pause_reason=NULL,updated_at=NOW() WHERE id=:id");
        $stmt->execute(['id' => $sourceId]);
        $this->audit('source_resumed', 'data_source', $sourceId, $this->sourceSummary($source), $after + ['reason' => $reason], $actorId);
        return ['source_id' => $sourceId, 'status' => 'resumed', 'errors' => []];
    }

    public function enableSource(int $sourceId, string $reason = 'Manual enable', bool $dryRun = false, ?int $actorId = null): array
    {
        $source = $this->findSource($sourceId);
        if (!$source) {
            return ['source_id' => $sourceId, 'status' => 'not_found', 'errors' => ['source_not_found']];
        }

        $after = ['is_active' => 1, 'is_paused' => 0, 'pause_reason' => null, 'disabled_reason' => null];
        if ($dryRun) {
            return ['source_id' => $sourceId, 'status' => 'dry_run', 'before' => $this->sourceSummary($source), 'after' => $after, 'errors' => []];
        }

        $stmt = $this->db->prepare("UPDATE data_sources SET is_active=1,is_paused=0,pause_reason=NULL,disabled_reason=NULL,updated_at=NOW() WHERE id=:id");
        $stmt->execute(['id' => $sourceId]);
        $this->audit('source_enabled', 'data_source', $sourceId, $this->sourceSummary($source), $after + ['reason' => $reason], $actorId);
        return ['source_id' => $sourceId, 'status' => 'enabled', 'errors' => []];
    }

    public function recordIncident(int $sourceId, string $incidentType, string $message, string $actionTaken = 'monitor', string $createdBy = 'system', ?int $actorId = null): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO source_incidents (source_id,incident_type,message,description,occurred_at,action_taken,created_by)
             VALUES (:source_id,:incident_type,:message,:description,NOW(),:action_taken,:created_by)"
        );
        $stmt->execute([
            'source_id' => $sourceId,
            'incident_type' => $this->normalizeIncidentType($incidentType),
            'message' => $message,
            'description' => $message,
            'action_taken' => $actionTaken,
            'created_by' => $createdBy,
        ]);
        $incidentId = (int)$this->db->lastInsertId();
        $this->audit('source_incident_recorded', 'source_incident', $incidentId, null, [
            'source_id' => $sourceId,
            'incident_type' => $this->normalizeIncidentType($incidentType),
            'message' => $message,
            'action_taken' => $actionTaken,
            'created_by' => $createdBy,
        ], $actorId);
        return ['incident_id' => $incidentId, 'source_id' => $sourceId, 'status' => 'recorded'];
    }

    private function findJob(int $jobId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM collector_jobs WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $jobId]);
        return $stmt->fetch() ?: null;
    }

    private function findSource(int $sourceId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM data_sources WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $sourceId]);
        return $stmt->fetch() ?: null;
    }

    private function jobSummary(array $job): array
    {
        return [
            'status' => $job['status'],
            'run_id' => $job['run_id'] ?? null,
            'attempt_count' => (int)($job['attempt_count'] ?? 0),
            'started_at' => $job['started_at'] ?? null,
            'completed_at' => $job['completed_at'] ?? null,
            'raw_count' => (int)$job['raw_count'],
            'valid_count' => (int)$job['valid_count'],
            'error_message' => $job['error_message'] ?? null,
        ];
    }

    private function sourceSummary(array $source): array
    {
        return [
            'is_active' => (int)$source['is_active'],
            'is_paused' => (int)($source['is_paused'] ?? 0),
            'pause_reason' => $source['pause_reason'] ?? null,
            'disabled_reason' => $source['disabled_reason'] ?? null,
        ];
    }

    private function normalizeIncidentType(string $type): string
    {
        $allowed = ['blocked', 'rate_limited', 'authentication_required', 'source_structure_changed', 'repeated_extraction_failure', 'manually_paused', 'manually_disabled', 'terms_change', 'quality_drop', 'manual_pause', 'other'];
        return in_array($type, $allowed, true) ? $type : 'other';
    }

    private function audit(string $action, string $entityType, ?int $entityId, ?array $before, array $after, ?int $actorId = null): void
    {
        $stmt = $this->db->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data) VALUES (:user_id,:action,:entity_type,:entity_id,:before_data,:after_data)");
        $stmt->execute([
            'user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_data' => json_encode($after, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
