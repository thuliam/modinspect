<?php
declare(strict_types=1);

namespace App\Services\Ops;

use PDO;

/**
 * LS-89 BE-3: the record of which migration files a database has run (table schema_migrations, migration 038).
 * On a database without the table every method is a harmless no-op, so the migration tools work before 038 is applied.
 */
final class MigrationLedger
{
    public function __construct(private PDO $db)
    {
    }

    public function available(): bool
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'")->fetchColumn() === 1;
    }

    /** the same checksum on Windows and Linux checkouts */
    public static function checksum(string $sql): string
    {
        return hash('sha256', str_replace("\r\n", "\n", $sql));
    }

    /** @return array<string,array{checksum:string,method:string,applied_at:string}> by file name */
    public function all(): array
    {
        if (!$this->available()) {
            return [];
        }
        $out = [];
        foreach ($this->db->query('SELECT filename, checksum, method, applied_at FROM schema_migrations ORDER BY filename')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string)$row['filename']] = ['checksum' => (string)$row['checksum'], 'method' => (string)$row['method'], 'applied_at' => (string)$row['applied_at']];
        }
        return $out;
    }

    /** $method 'run' = the file was executed now; 'recorded' = its effect was already there and a person asked to write it down. The first record is kept. */
    public function record(string $filename, string $sql, string $method = 'run'): bool
    {
        if (!$this->available()) {
            return false;
        }
        $stmt = $this->db->prepare('INSERT IGNORE INTO schema_migrations (filename, checksum, method) VALUES (?,?,?)');
        $stmt->execute([basename($filename), self::checksum($sql), $method === 'recorded' ? 'recorded' : 'run']);
        return $stmt->rowCount() === 1;
    }
}
