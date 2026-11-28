<?php
declare(strict_types=1);

namespace App\Services\Review;

use App\Core\Database;
use App\Services\Pricing\BrandNewClaim;
use PDO;

/**
 * LS-90 T2: the pending real listings as a CSV, those that would change a price first. For checking only: nothing reads the file back
 * and nothing is approved. It contains collected listing titles, so it is for signed-in reviewers and must not be published.
 */
final class ReviewExport
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        global $config;
        $this->db = $db ?? Database::connection($config['db']);
    }

    /** @return list<array<string,string|int|float>> */
    public function pendingRows(): array
    {
        $impact = (new PriceImpact($this->db))->pending();
        $eligible = array_flip($impact['eligible']);
        $first = array_flip($impact['first_price']);
        $stmt = $this->db->query("SELECT o.id,p.full_name,c.name category,o.price_type,COALESCE(o.price_value,o.asking_price) price,o.observed_at,r.raw_title,s.source_key,s.name source_name
            FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id
            LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id
            WHERE o.verified_status='pending' ORDER BY p.full_name,o.id");
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (DatasetLabel::of((string)$r['source_key'], (string)$r['source_name']) !== DatasetLabel::REAL) {
                continue;
            }
            $id = (int)$r['id'];
            $title = (string)$r['raw_title'];
            $warnings = [];
            if (BrandNewClaim::detect($title) !== null) {
                $warnings[] = 'BRAND_NEW_CLAIM';
            }
            if (preg_match('/ขายแล้ว|\bsold\b|จองแล้ว|reserved|ติดจอง/iu', $title) === 1) {
                $warnings[] = 'SOLD_OR_RESERVED';
            }
            $rows[] = [
                'observation_id' => $id,
                'impact' => isset($first[$id]) ? 'first_price' : (isset($eligible[$id]) ? 'price' : 'none'),
                'category' => (string)$r['category'],
                'model' => (string)$r['full_name'],
                'price_type' => (string)$r['price_type'],
                'price' => (float)$r['price'],
                'observed_at' => (string)$r['observed_at'],
                'warnings' => implode(' ', $warnings),
                'source' => (string)$r['source_key'],
                'title' => $title,
            ];
        }
        $order = ['first_price' => 0, 'price' => 1, 'none' => 2];
        usort($rows, static fn(array $a, array $b): int => [$order[$a['impact']], $a['model'], $a['observation_id']] <=> [$order[$b['impact']], $b['model'], $b['observation_id']]);
        return $rows;
    }

    /** UTF-8 with a byte order mark so a spreadsheet reads Thai; a text cell that could be read as a formula is quoted off */
    public function pendingCsv(): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['observation_id', 'impact', 'category', 'model', 'price_type', 'price', 'observed_at', 'warnings', 'source', 'title'], ',', '"', '');
        foreach ($this->pendingRows() as $row) {
            fputcsv($out, array_map(static fn(mixed $v): mixed => is_string($v) && preg_match('/^\s*[=+\-@\t\r]/', $v) === 1 ? "'" . $v : $v, array_values($row)), ',', '"', '');
        }
        rewind($out);
        $csv = (string)stream_get_contents($out);
        fclose($out);
        return $csv;
    }
}
