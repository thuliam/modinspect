<?php
declare(strict_types=1);

namespace App\Services\Ops;

use PDO;
use RuntimeException;

/**
 * Logical database backup and a guarded restore (LS-2 H-11). Wraps mysqldump / mysql.
 * - The password never appears on a command line: it is passed through the child's MYSQL_PWD environment variable.
 * - A restore can only target a database whose name ends in _test or _restore and that differs from the source, so a restore can never overwrite live data.
 * - A backup holds everything in the database (including password hashes and raw listing text): treat the file as private; storage/backups/ is git-ignored.
 */
final class DatabaseBackup
{
    public static function binary(string $name): ?string
    {
        $exe = $name . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        $dirs = [];
        if (($d = getenv('MODINSPECT_MYSQL_BIN_DIR')) !== false && $d !== '') { $dirs[] = $d; }
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $p) { if ($p !== '') { $dirs[] = $p; } }
        foreach (glob('C:/laragon/bin/mysql/*/bin', GLOB_ONLYDIR) ?: [] as $p) { $dirs[] = $p; }
        foreach ($dirs as $dir) {
            $f = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $exe;
            if (is_file($f)) { return $f; }
        }
        return null;
    }

    public static function assertSafeTarget(string $sourceDb, string $targetDb): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $targetDb)) { throw new RuntimeException('RESTORE_TARGET_INVALID_NAME'); }
        if (strcasecmp($sourceDb, $targetDb) === 0) { throw new RuntimeException('RESTORE_TARGET_IS_SOURCE'); }
        if (!str_ends_with($targetDb, '_test') && !str_ends_with($targetDb, '_restore')) { throw new RuntimeException('RESTORE_TARGET_MUST_END_WITH_TEST_OR_RESTORE'); }
    }

    /**
     * @param array{host:string,port:string|int,database:string,username:string,password:string} $db
     * @return array{ok:bool,bytes:int,error:?string}
     */
    public static function backup(array $db, string $outFile): array
    {
        $bin = self::binary('mysqldump');
        if ($bin === null) { return ['ok' => false, 'bytes' => 0, 'error' => 'MYSQLDUMP_NOT_FOUND']; }
        $dir = dirname($outFile);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) { return ['ok' => false, 'bytes' => 0, 'error' => 'OUTPUT_DIR_NOT_WRITABLE']; }
        $base = [$bin, '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['username'], '--single-transaction', '--routines', '--triggers',
            '--default-character-set=utf8mb4', '--result-file=' . $outFile];
        // mysqldump 8 and MariaDB's mysqldump disagree about two options (--no-tablespaces, --column-statistics): try the variants in turn
        $variants = [['--no-tablespaces'], ['--no-tablespaces', '--column-statistics=0'], ['--column-statistics=0'], []];
        $lastError = 'MYSQLDUMP_FAILED';
        foreach ($variants as $extra) {
            $p = proc_open(array_merge($base, $extra, [$db['database']]), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv() ?: [], ['MYSQL_PWD' => (string)$db['password']]));
            if (!is_resource($p)) { return ['ok' => false, 'bytes' => 0, 'error' => 'MYSQLDUMP_NOT_STARTED']; }
            stream_get_contents($pipes[1]);
            $err = trim((string)stream_get_contents($pipes[2]));
            $code = proc_close($p);
            $bytes = is_file($outFile) ? (int)filesize($outFile) : 0;
            if ($code === 0 && $bytes > 0) {
                // password hashes and raw listing text inside: owner-only. Fail closed: a dump that cannot be restricted is deleted, not kept readable.
                $chmodOk = @chmod($outFile, 0600);
                clearstatcache(true, $outFile);
                $mode = (int)(@fileperms($outFile) ?: 0) & 0777;
                if (PHP_OS_FAMILY !== 'Windows' && ($mode & 0077) !== 0) {
                    @unlink($outFile);
                    return ['ok' => false, 'bytes' => 0, 'error' => sprintf('BACKUP_PERMISSIONS_NOT_RESTRICTED:chmod=%s mode=%s owner=%s me=%s', $chmodOk ? 'true' : 'false', decoct($mode), (string)@fileowner($outFile), (string)getmyuid())];
                }
                return ['ok' => true, 'bytes' => $bytes, 'error' => null];
            }
            @unlink($outFile);
            $lastError = 'MYSQLDUMP_FAILED:' . substr(preg_replace('/\s+/', ' ', $err) ?? '', 0, 160);
        }
        return ['ok' => false, 'bytes' => 0, 'error' => $lastError];
    }

    /**
     * @param array{host:string,port:string|int,database:string,username:string,password:string} $db source connection settings; the credentials are reused for the target
     * @return array{ok:bool,error:?string}
     */
    public static function restore(array $db, string $file, string $targetDb): array
    {
        self::assertSafeTarget($db['database'], $targetDb);
        if (!is_file($file) || !is_readable($file)) { return ['ok' => false, 'error' => 'BACKUP_FILE_NOT_READABLE']; }
        $bin = self::binary('mysql');
        if ($bin === null) { return ['ok' => false, 'error' => 'MYSQL_CLIENT_NOT_FOUND']; }
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']), $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DROP DATABASE IF EXISTS `' . $targetDb . '`');
        $pdo->exec('CREATE DATABASE `' . $targetDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $cmd = [$bin, '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['username'], '--default-character-set=utf8mb4', $targetDb];
        $in = fopen($file, 'rb');
        $p = proc_open($cmd, [0 => $in, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv() ?: [], ['MYSQL_PWD' => (string)$db['password']]));
        if (!is_resource($p)) { return ['ok' => false, 'error' => 'MYSQL_NOT_STARTED']; }
        stream_get_contents($pipes[1]);
        $err = trim((string)stream_get_contents($pipes[2]));
        $code = proc_close($p);
        fclose($in);
        return $code === 0 ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'MYSQL_FAILED:' . substr(preg_replace('/\s+/', ' ', $err) ?? '', 0, 160)];
    }

    /** @return array<string,int> exact row count per base table of the given database (views are skipped) */
    public static function tableCounts(PDO $pdo, string $database): array
    {
        $out = [];
        $t = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = :d AND table_type = 'BASE TABLE' ORDER BY table_name");
        $t->execute(['d' => $database]);
        foreach ($t->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $out[(string)$name] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $database . '`.`' . $name . '`')->fetchColumn();
        }
        return $out;
    }
}
