<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Disposable price data for tests that must commit (transaction and rollback checks cannot run inside an outer transaction).
 * It creates its own model, source and reviewer in the test database and removes everything it made in cleanup().
 */
final class PriceFixture
{
    public PDO $db;
    public int $productId;
    public int $sourceId;
    public int $userId;
    private string $tag;
    private int $n = 0;

    public function __construct(string $tag)
    {
        global $config;
        $this->db = Database::connection($config['db']);
        $this->tag = $tag . '-' . bin2hex(random_bytes(4));
        $template = $this->db->query('SELECT category_id, brand_id FROM products ORDER BY id LIMIT 1')->fetch();
        $this->db->prepare("INSERT INTO products (category_id,brand_id,model_name,full_name,slug,is_active) VALUES (?,?,?,?,?,1)")
            ->execute([$template['category_id'], $template['brand_id'], $this->tag, 'Fixture ' . $this->tag, 'fixture-' . $this->tag]);
        $this->productId = (int)$this->db->lastInsertId();
        $this->sourceId = $this->source('fixture_ls89_' . $this->tag, 'LS89 fixture ' . $this->tag, 'ls89.example');
        $this->db->prepare("INSERT INTO users (name,email,password_hash,role,is_active) VALUES (?,?,?,'reviewer',1)")
            ->execute(['LS89 reviewer', $this->tag . '@example.test', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $this->userId = (int)$this->db->lastInsertId();
    }

    /** @var list<int> */
    private array $sources = [];

    public function source(string $key, string $name, string $domain): int
    {
        $this->db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,risk_level,is_active,last_success_at) VALUES (?,?,?,'research','manual','low',1,NOW())")
            ->execute([$key, $name, $domain]);
        $id = (int)$this->db->lastInsertId();
        $this->sources[] = $id;
        return $id;
    }

    public function observation(float $price, string $status = 'approved', ?int $sourceId = null, string $observedAt = ''): int
    {
        $this->n++;
        $observedAt = $observedAt !== '' ? $observedAt : date('Y-m-d H:i:s');
        $source = $sourceId ?? $this->sourceId;
        $this->db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (?,?,?,?,NOW(),'processed')")
            ->execute([$source, hash('sha256', $this->tag . '-' . $this->n), 'Fixture listing ' . $this->n, (string)$price]);
        $this->db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at) VALUES (?,?,'asking',?,?,'THB','good','single_item',0,0,0,3,95,?,?)")
            ->execute([(int)$this->db->lastInsertId(), $this->productId, $price, $price, $status, $observedAt]);
        return (int)$this->db->lastInsertId();
    }

    public function count(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** makes the next insert into $table fail, to prove that a unit of work leaves nothing behind */
    public function breakInsert(string $table): void
    {
        $this->db->exec("CREATE TRIGGER ls89_forced_failure BEFORE INSERT ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ls89 forced failure'");
    }

    public function repair(): void
    {
        $this->db->exec('DROP TRIGGER IF EXISTS ls89_forced_failure');
    }

    public function cleanup(): void
    {
        $this->repair();
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $pid = $this->productId;
        $this->db->exec("DELETE FROM price_snapshot_observations WHERE snapshot_id IN (SELECT id FROM price_indices WHERE product_id=$pid)");
        foreach (['price_indices', 'price_histories', 'price_trend_points'] as $table) {
            $this->db->exec("DELETE FROM `$table` WHERE product_id=$pid");
        }
        $ids = $this->sources === [] ? '0' : implode(',', $this->sources);
        $observations = "SELECT o.id FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id IN ($ids)";
        $list = implode(',', array_map('intval', $this->db->query($observations)->fetchAll(PDO::FETCH_COLUMN))) ?: '0';
        $this->db->exec("DELETE FROM review_corrections WHERE price_observation_id IN ($list)");
        $this->db->exec("DELETE FROM observation_review_decisions WHERE price_observation_id IN ($list)");
        $this->db->exec("DELETE FROM audit_logs WHERE user_id={$this->userId} OR (entity_type='price_observation' AND entity_id IN ($list))");
        $this->db->exec("DELETE FROM price_observations WHERE id IN ($list)");
        $this->db->exec("DELETE FROM raw_price_observations WHERE source_id IN ($ids)");
        $this->db->exec("DELETE FROM data_sources WHERE id IN ($ids)");
        $this->db->exec("DELETE FROM products WHERE id=$pid");
        $this->db->exec("DELETE FROM users WHERE id={$this->userId}");
    }
}
