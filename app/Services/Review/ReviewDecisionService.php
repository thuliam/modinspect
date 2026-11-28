<?php
declare(strict_types=1);

namespace App\Services\Review;

use App\Core\Database;
use App\Services\Provenance\ObservationProvenanceService;
use PDO;
use Throwable;

/**
 * LS-89 BE-1: a human review decision as one unit of work. The status change, the decision record, the link to the corrections and the
 * audit line (with the acting user) are written in one transaction: either all of them are stored or none.
 */
final class ReviewDecisionService
{
    public const DECISIONS = ['approved', 'rejected', 'excluded'];
    /** where an approved observation may go when it is withdrawn; 'pending' sends it back to the queue so it can be corrected */
    public const WITHDRAW_TO = ['pending', 'rejected', 'excluded'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        global $config;
        $this->db = $db ?? Database::connection($config['db']);
    }

    /**
     * Decides a pending observation.
     * @return array{ok:bool,error:?string,errors:list<string>,product_ids:list<int>} product_ids are the models whose price inputs may have changed
     */
    public function decide(int $id, string $decision, string $notes = '', ?int $actorId = null): array
    {
        if (!in_array($decision, self::DECISIONS, true)) {
            return $this->fail('UNSUPPORTED_DECISION');
        }
        return $this->transaction(function () use ($id, $decision, $notes, $actorId): array {
            $current = $this->lockedRow($id);
            if ($current === null) {
                return $this->fail('OBSERVATION_NOT_FOUND');
            }
            if (($current['verified_status'] ?? '') !== 'pending') {
                return $this->fail('OBSERVATION_NOT_PENDING');
            }
            $before = (int)$current['product_id'];
            $applied = ['ok' => true, 'values' => [], 'quality_flags' => []];
            if ($decision === 'approved') {
                if (!$this->hasEnoughProvenance($id)) {
                    return $this->fail('INSUFFICIENT_PROVENANCE');
                }
                $applied = (new ReviewCorrectionService())->applyApprovedValues($id);
                if (($applied['ok'] ?? false) !== true) {
                    return $this->fail('INVALID_FINAL_VALUES', array_values($applied['errors'] ?? []));
                }
                $current = $this->lockedRow($id) ?? $current;
            }
            $update = $this->db->prepare("UPDATE price_observations SET verified_status=:status,updated_at=NOW() WHERE id=:id AND verified_status='pending'");
            $update->execute(['status' => $decision, 'id' => $id]);
            if ($update->rowCount() !== 1) {
                return $this->fail('OBSERVATION_NOT_PENDING');
            }
            $this->record($current, $decision, $actorId, $notes, 'review_' . $decision, ['verified_status' => $decision, 'final_values' => $applied['values'] ?? [], 'quality_flags' => $applied['quality_flags'] ?? []]);
            return ['ok' => true, 'error' => null, 'errors' => [], 'product_ids' => array_values(array_unique([$before, (int)$current['product_id']]))];
        });
    }

    /**
     * LS-89 BE-2: takes an approved observation out of the price data. A reason is required and kept in the decision record and the audit line.
     * @return array{ok:bool,error:?string,errors:list<string>,product_ids:list<int>}
     */
    public function withdraw(int $id, string $to, string $reason, int $actorId): array
    {
        if (!in_array($to, self::WITHDRAW_TO, true)) {
            return $this->fail('UNSUPPORTED_DECISION');
        }
        $reason = trim($reason);
        if ($reason === '') {
            return $this->fail('REASON_REQUIRED');
        }
        return $this->transaction(function () use ($id, $to, $reason, $actorId): array {
            $current = $this->lockedRow($id);
            if ($current === null) {
                return $this->fail('OBSERVATION_NOT_FOUND');
            }
            if (($current['verified_status'] ?? '') !== 'approved') {
                return $this->fail('OBSERVATION_NOT_APPROVED');
            }
            $update = $this->db->prepare("UPDATE price_observations SET verified_status=:status,updated_at=NOW() WHERE id=:id AND verified_status='approved'");
            $update->execute(['status' => $to, 'id' => $id]);
            if ($update->rowCount() !== 1) {
                return $this->fail('OBSERVATION_NOT_APPROVED');
            }
            $this->record($current, $to, $actorId, $reason, 'review_withdrawn', ['verified_status' => $to, 'withdrawn_from' => 'approved']);
            return ['ok' => true, 'error' => null, 'errors' => [], 'product_ids' => [(int)$current['product_id']]];
        });
    }

    /** same rule as before LS-89: a real listing needs a listing-level or owner-text provenance before it can be approved */
    private function hasEnoughProvenance(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT o.id,o.asking_price,o.price_value,o.raw_observation_id,r.raw_title,r.raw_price_text,r.source_url_encrypted,r.external_reference_hash,s.source_key,s.name source_name,e.excerpt FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN market_evidence e ON e.raw_observation_id=r.id WHERE o.id=:id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch() ?: [];
        if (DatasetLabel::of((string)($row['source_key'] ?? ''), (string)($row['source_name'] ?? '')) !== DatasetLabel::REAL) {
            return true;
        }
        $provenance = new ObservationProvenanceService();
        return $provenance->isListingLevel($row) || $provenance->isOwnerTextNoUrl($row);
    }

    private function record(array $before, string $status, ?int $actorId, string $notes, string $action, array $after): void
    {
        $id = (int)$before['id'];
        // a row sent back to the queue is "review required"; every other non-approval is stored as rejected (the column has no "excluded")
        [$lane, $verdict] = match ($status) {
            'approved' => ['green', 'approved'],
            'pending' => ['amber', 'review_required'],
            default => ['red', 'rejected'],
        };
        $decision = $this->db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes,human_value,reviewer_id,notes) VALUES (:observation_id,:raw_id,:lane,:decision,:reasons,:human_value,:reviewer_id,:notes)");
        $decision->execute([
            'observation_id' => $id,
            'raw_id' => $before['raw_observation_id'],
            'lane' => $lane,
            'decision' => $verdict,
            'reasons' => json_encode(['human_' . $status]),
            'human_value' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'reviewer_id' => $actorId,
            'notes' => $notes,
        ]);
        $this->db->prepare("UPDATE review_corrections SET review_decision_id=:review_id WHERE price_observation_id=:id AND review_decision_id IS NULL")
            ->execute(['review_id' => (int)$this->db->lastInsertId(), 'id' => $id]);
        $this->db->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data) VALUES (:user_id,:action,'price_observation',:id,:before_data,:after_data)")
            ->execute([
                'user_id' => $actorId,
                'action' => $action,
                'id' => $id,
                'before_data' => json_encode($before, JSON_UNESCAPED_UNICODE),
                'after_data' => json_encode(['notes' => $notes] + $after, JSON_UNESCAPED_UNICODE),
            ]);
    }

    private function lockedRow(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM price_observations WHERE id=:id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** runs $work in a transaction (or inside the caller's one); a failed result or an exception leaves nothing behind */
    private function transaction(callable $work): array
    {
        $own = !$this->db->inTransaction();
        if ($own) {
            $this->db->beginTransaction();
        } else {
            $this->db->exec('SAVEPOINT review_decision');
        }
        try {
            $result = $work();
            if ($result['ok']) {
                $own ? $this->db->commit() : $this->db->exec('RELEASE SAVEPOINT review_decision');
            } else {
                $own ? $this->db->rollBack() : $this->db->exec('ROLLBACK TO SAVEPOINT review_decision');
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $own ? $this->db->rollBack() : $this->db->exec('ROLLBACK TO SAVEPOINT review_decision');
            }
            throw $e;
        }
    }

    private function fail(string $error, array $errors = []): array
    {
        return ['ok' => false, 'error' => $error, 'errors' => $errors, 'product_ids' => []];
    }
}
