<?php
declare(strict_types=1);

namespace App\Services\Member;

use PDO;

/**
 * Marketplace P1-A: versioned legal documents and who accepted which version. Publishing is a deliberate human step (publish() needs an admin user id and
 * refuses an empty body); this service never creates or publishes a document by itself, and nothing is "required" until a document is published.
 */
final class LegalDocuments
{
    public const TYPES = ['buyer_terms', 'seller_terms', 'privacy', 'community_rules', 'subscription_terms', 'refund_policy', 'takedown_policy'];

    public function __construct(private PDO $db, private MemberAudit $audit) {}

    public function createDraft(string $type, string $version, string $title, string $body): int
    {
        if (!in_array($type, self::TYPES, true)) { throw new \InvalidArgumentException('DOC_TYPE'); }
        $this->db->prepare("INSERT INTO legal_documents (doc_type, version, title, body_text, status) VALUES (:t, :v, :ti, :b, 'draft')")
            ->execute(['t' => $type, 'v' => substr($version, 0, 20), 'ti' => substr($title, 0, 160), 'b' => $body]);
        return (int)$this->db->lastInsertId();
    }

    /** Publishes one draft and retires the previously published version of the same type, atomically. */
    public function publish(int $documentId, int $adminUserId): void
    {
        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $stmt = $this->db->prepare('SELECT id, doc_type, status, body_text FROM legal_documents WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $documentId]);
            $d = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$d || $d['status'] !== 'draft') { throw new \RuntimeException('NOT_A_DRAFT'); }
            if (trim((string)$d['body_text']) === '') { throw new \RuntimeException('EMPTY_BODY'); }
            // LS-90: the drafts mark what the owner still has to decide as [...]; such a text is not ready to publish
            if (preg_match('/\[[^\]
]{2,}\](?!\()/u', (string)$d['body_text']) === 1) { throw new \RuntimeException('UNFILLED_BLANK'); }
            $this->db->prepare("UPDATE legal_documents SET status = 'retired' WHERE doc_type = :t AND status = 'published'")->execute(['t' => $d['doc_type']]);
            $this->db->prepare("UPDATE legal_documents SET status = 'published', published_at = NOW(), published_by_user_id = :u WHERE id = :id")->execute(['u' => $adminUserId, 'id' => $documentId]);
            $this->audit->record(null, 'admin', $adminUserId, 'legal.published', 'legal_document', $documentId);
            if ($own) { $this->db->commit(); }
        } catch (\Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> every version, newest first, without the body */
    public function all(): array
    {
        return $this->db->query('SELECT id, doc_type, version, title, status, published_at, created_at FROM legal_documents ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return ?array<string,mixed> */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, doc_type, version, title, body_text, status, published_at FROM legal_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string,mixed>> the current published document of each type */
    public function published(): array
    {
        return $this->db->query("SELECT id, doc_type, version, title, published_at FROM legal_documents WHERE status = 'published' ORDER BY doc_type")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return ?array<string,mixed> the current published document of one type, with its text */
    public function current(string $type): ?array
    {
        $stmt = $this->db->prepare("SELECT id, doc_type, version, title, body_text, published_at FROM legal_documents WHERE doc_type = :t AND status = 'published' ORDER BY published_at DESC, id DESC LIMIT 1");
        $stmt->execute(['t' => $type]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string,mixed>> published documents the member has not accepted yet */
    public function pendingFor(int $memberId, ?array $onlyTypes = null): array
    {
        $stmt = $this->db->prepare("SELECT d.id, d.doc_type, d.version, d.title FROM legal_documents d
            LEFT JOIN legal_acceptances a ON a.document_id = d.id AND a.member_id = :m
            WHERE d.status = 'published' AND a.id IS NULL ORDER BY d.doc_type");
        $stmt->execute(['m' => $memberId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $onlyTypes === null ? $rows : array_values(array_filter($rows, static fn(array $r): bool => in_array($r['doc_type'], $onlyTypes, true)));
    }

    /** Records acceptance of a PUBLISHED document only (a draft or retired version cannot be accepted). Idempotent. */
    public function accept(int $memberId, int $documentId): bool
    {
        $s = $this->db->prepare("SELECT id FROM legal_documents WHERE id = :id AND status = 'published'");
        $s->execute(['id' => $documentId]);
        if (!$s->fetchColumn()) { return false; }
        $ins = $this->db->prepare('INSERT IGNORE INTO legal_acceptances (member_id, document_id) VALUES (:m, :d)');
        $ins->execute(['m' => $memberId, 'd' => $documentId]);
        if ($ins->rowCount() > 0) { $this->audit->record($memberId, 'member', $memberId, 'legal.accepted', 'legal_document', $documentId); }
        return true;
    }

    /** True only if the member accepted the CURRENT published version of the type. */
    public function hasAccepted(int $memberId, string $type): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM legal_acceptances a JOIN legal_documents d ON d.id = a.document_id WHERE a.member_id = :m AND d.doc_type = :t AND d.status = 'published' LIMIT 1");
        $stmt->execute(['m' => $memberId, 't' => $type]);
        return (bool)$stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function acceptances(int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT d.doc_type, d.version, d.title, a.accepted_at FROM legal_acceptances a JOIN legal_documents d ON d.id = a.document_id WHERE a.member_id = :m ORDER BY a.id DESC');
        $stmt->execute(['m' => $memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
