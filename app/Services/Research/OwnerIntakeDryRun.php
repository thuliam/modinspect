<?php
declare(strict_types=1);

namespace App\Services\Research;

use App\Core\Database;
use App\Services\Collection\OfflineEvidenceImportService;
use PDO;

/**
 * The read-only first half of the owner intake, as one call (LS-3 J-3): prepare the rows of a sanitised CSV, drop the listings already stored,
 * and run the importer in DRY-RUN mode. It reports counts only (never a title, a price or a seller) and writes nothing to the database:
 * the temporary import file lives in storage/import_uploads and is deleted before the call returns.
 * Importing for real and applying approvals stay on the command line in this slice.
 */
final class OwnerIntakeDryRun
{
    public const REQUIRED_COLUMNS = ['row_id', 'note_date', 'category', 'title', 'asking_price_thb', 'listing_type', 'enrichment_status'];
    public const MAX_BYTES = 5_242_880;

    private PDO $db;
    public function __construct(?PDO $db = null) { global $config; $this->db = $db ?? Database::connection($config['db']); }

    /** @return list<string> required columns missing from the CSV header (empty = usable); ['unreadable'] when the file cannot be read */
    public static function headerProblems(string $csvPath): array
    {
        $fh = @fopen($csvPath, 'rb');
        if ($fh === false) { return ['unreadable']; }
        $h = fgetcsv($fh, 0, ',', '"', '');
        fclose($fh);
        if ($h === false) { return ['empty file']; }
        $h[0] = ltrim((string)$h[0], "\xEF\xBB\xBF");
        return array_values(array_diff(self::REQUIRED_COLUMNS, $h));
    }

    /**
     * @return array{ok:bool,error:?string,prepared:int,skipped:array<string,int>,already_present:int,new:int,import:array{valid_rows:int,invalid_rows:int,duplicate_rows:int},row_errors:array<string,int>}
     */
    public function run(string $csvPath, ?string $category = null, bool $spec = false, ?string $workDir = null): array
    {
        $empty = ['ok' => false, 'error' => null, 'prepared' => 0, 'skipped' => [], 'already_present' => 0, 'new' => 0, 'import' => ['valid_rows' => 0, 'invalid_rows' => 0, 'duplicate_rows' => 0], 'row_errors' => []];
        if ($problems = self::headerProblems($csvPath)) { return ['error' => 'MISSING_COLUMNS:' . implode(',', $problems)] + $empty; }
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
        $prep = OwnerIntakePreparer::prepare($csvPath, $category, $spec, 2000, $spec ? OwnerIntakePreparer::knownSpecClasses($root) : []);
        $res = OwnerIntakeDeduper::filter($prep['records'], OwnerIntakeDeduper::existingKeys($this->db));
        $out = ['ok' => true, 'error' => null, 'prepared' => count($prep['records']), 'skipped' => $prep['skipped'], 'already_present' => (int)$res['already_present'], 'new' => count($res['kept'])] + $empty;
        $out['import'] = ['valid_rows' => 0, 'invalid_rows' => 0, 'duplicate_rows' => 0];
        if ($res['kept'] === []) { return $out; }
        $dir = $workDir ?? ($root . '/storage/import_uploads');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return ['error' => 'WORK_DIR_NOT_WRITABLE'] + $empty; }
        $file = $dir . '/intake-dryrun-' . bin2hex(random_bytes(6)) . '.json';
        try {
            file_put_contents($file, json_encode(['records' => $res['kept']], JSON_UNESCAPED_UNICODE));
            $sum = (new OfflineEvidenceImportService())->import($file, 'owner_text', true, 2000);
        } finally {
            @unlink($file);
        }
        $out['import'] = ['valid_rows' => (int)$sum['valid_rows'], 'invalid_rows' => (int)$sum['invalid_rows'], 'duplicate_rows' => (int)$sum['duplicate_rows']];
        foreach ($sum['row_errors'] ?? [] as $e) { foreach ((array)($e['errors'] ?? []) as $code) { $out['row_errors'][(string)$code] = ($out['row_errors'][(string)$code] ?? 0) + 1; } }
        return $out;
    }
}
