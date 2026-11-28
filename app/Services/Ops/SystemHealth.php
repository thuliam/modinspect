<?php
declare(strict_types=1);

namespace App\Services\Ops;

use App\Services\Pricing\PublicSnapshot;
use PDO;
use Throwable;

/**
 * LS-89 BE-3: the detailed health list for signed-in admins (the public /health stays a yes/no). Each check is ok, warn or fail with a short
 * detail made of counts, names of migration files and relative folder names. No credential, host name, server path or error text.
 */
final class SystemHealth
{
    public const MIGRATIONS_FROM = 20;
    public const FOLDERS = ['storage/logs', 'storage/cache', 'storage/backups', 'storage/import_uploads', 'storage/uploads'];
    public const EXTENSIONS = ['pdo_mysql' => 'fail', 'mbstring' => 'fail', 'openssl' => 'fail', 'gd' => 'warn', 'curl' => 'warn'];
    public const JOB_MAX_AGE_HOURS = 26;
    public const BACKUP_MAX_AGE_HOURS = 48;

    public function __construct(private PDO $db, private ?string $root = null)
    {
        $this->root ??= ROOT_PATH;
    }

    /** @return list<array{code:string,status:string,detail:string}> */
    public function checks(bool $withJobs = true): array
    {
        $checks = [$this->check('database', 'ok', 'connected')];
        $checks[] = $this->migrations();
        foreach (self::FOLDERS as $folder) {
            $path = $this->root . '/' . $folder;
            $ok = is_dir($path) && is_writable($path);
            $checks[] = $this->check('folder:' . $folder, $ok ? 'ok' : 'warn', $ok ? 'writable' : (is_dir($path) ? 'not writable' : 'missing'));
        }
        foreach (self::EXTENSIONS as $name => $severity) {
            $loaded = extension_loaded($name);
            $checks[] = $this->check('php:' . $name, $loaded ? 'ok' : $severity, $loaded ? 'loaded' : 'not loaded');
        }
        $checks[] = $this->backups();
        $checks[] = $this->hiddenPrices();
        if ($withJobs) {
            $latest = (new JobRunner($this->db))->latest();
            foreach (DailyJobs::JOBS as $job) {
                $checks[] = $this->job($job, $latest[$job] ?? null);
            }
        }
        return $checks;
    }

    private function migrations(): array
    {
        try {
            $existing = [];
            foreach ($this->db->query('SELECT LOWER(TABLE_NAME) t, LOWER(COLUMN_NAME) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $existing[$r['t']][] = $r['c'];
            }
            $files = [];
            foreach (glob($this->root . '/database/migrations/*.sql') ?: [] as $file) {
                $files[basename($file)] = (string)file_get_contents($file);
            }
            $plan = MigrationPlan::build($files, $existing, self::MIGRATIONS_FROM, (new MigrationLedger($this->db))->all());
            $missing = array_values(array_map(static fn(array $i): string => $i['file'], array_filter($plan['items'], static fn(array $i): bool => in_array($i['action'], ['run', 'blocked'], true))));
            if (!$plan['safe']) {
                return $this->check('migrations', 'fail', 'partly applied: ' . implode(', ', $missing));
            }
            return $this->check('migrations', $missing === [] ? 'ok' : 'warn', $missing === [] ? 'up to date' : 'not applied: ' . implode(', ', $missing));
        } catch (Throwable) {
            return $this->check('migrations', 'fail', 'could not be read');
        }
    }

    private function backups(): array
    {
        $files = glob($this->root . '/storage/backups/*.sql') ?: [];
        if ($files === []) {
            return $this->check('backup_file', 'warn', 'no backup file on this server');
        }
        clearstatcache();
        $age = (int)floor((time() - max(array_map('filemtime', $files))) / 3600);
        return $this->check('backup_file', $age <= self::BACKUP_MAX_AGE_HOURS ? 'ok' : 'warn', 'newest is ' . $age . ' h old, ' . count($files) . ' kept');
    }

    private function hiddenPrices(): array
    {
        try {
            $hidden = (int)$this->db->query("SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND " . PublicSnapshot::idSql('p.id') . " IS NULL
                AND EXISTS (SELECT 1 FROM price_indices h WHERE h.product_id = p.id AND h.product_variant_id IS NULL AND h.price_type = 'asking' AND COALESCE(h.provenance_status, 'legacy_unavailable') <> 'legacy_unavailable')")->fetchColumn();
            return $this->check('hidden_prices', $hidden === 0 ? 'ok' : 'warn', $hidden . ' model(s) have a hidden price');
        } catch (Throwable) {
            return $this->check('hidden_prices', 'fail', 'could not be read');
        }
    }

    private function job(string $name, ?array $run): array
    {
        if ($run === null) {
            return $this->check('job:' . $name, 'warn', 'never recorded');
        }
        $age = (int)floor((time() - strtotime((string)$run['started_at'])) / 3600);
        if ($run['status'] === 'failed') {
            return $this->check('job:' . $name, 'fail', (string)$run['error_code'] . ', ' . $age . ' h ago');
        }
        if ($run['status'] === 'running') {
            return $this->check('job:' . $name, $age >= 2 ? 'fail' : 'ok', 'started ' . $age . ' h ago, not finished');
        }
        return $this->check('job:' . $name, $age <= self::JOB_MAX_AGE_HOURS ? 'ok' : 'warn', 'ran ' . $age . ' h ago');
    }

    private function check(string $code, string $status, string $detail): array
    {
        return ['code' => $code, 'status' => $status, 'detail' => $detail];
    }
}
