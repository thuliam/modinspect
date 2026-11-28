<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\PublicSnapshot;
use App\Services\Pricing\SnapshotRecompute;
use PDO;

/**
 * LS-89 BE-2: what the public site shows for each model and what a calculation made now would store. Read-only.
 *
 * State per model:
 *  ok        a public price is shown and a calculation now would give the same
 *  outdated  a public price is shown but a calculation now differs (or the model has fallen below the minimum)
 *  hidden    a price was calculated before but is not shown, because listings behind it changed
 *  ready     no price yet and there are now enough listings
 *  none      no price and not enough listings
 */
final class PriceStatus
{
    public const STATES = ['hidden', 'outdated', 'ready', 'ok', 'none'];

    private PDO $db;
    private PriceSnapshotService $snapshots;

    public function __construct(?PDO $db = null)
    {
        global $config;
        $this->db = $db ?? Database::connection($config['db']);
        $this->snapshots = new PriceSnapshotService();
    }

    /** @return array{counts:array<string,int>,rows:list<array>} rows are every model that is not in state ok or none, most urgent first */
    public function overview(): array
    {
        $shown = [];
        $sql = "SELECT p.id, pi.median, pi.valid_sample_size, pi.last_calculated_at FROM products p LEFT JOIN price_indices pi ON pi.id = " . PublicSnapshot::idSql('p.id') . " WHERE p.is_active = 1";
        foreach ($this->db->query($sql)->fetchAll() as $r) {
            $shown[(int)$r['id']] = $r;
        }
        $counts = array_fill_keys(self::STATES, 0);
        $rows = [];
        foreach ((new SnapshotRecompute($this->db, $this->snapshots))->plan() as $plan) {
            $public = $shown[$plan['product_id']] ?? ['median' => null, 'valid_sample_size' => null, 'last_calculated_at' => null];
            $state = self::state($public['median'] !== null, $plan['status']);
            $counts[$state]++;
            if ($state !== 'ok' && $state !== 'none') {
                $rows[] = $plan + [
                    'state' => $state,
                    'public_median' => $public['median'] === null ? null : (float)$public['median'],
                    'public_sample' => $public['valid_sample_size'] === null ? null : (int)$public['valid_sample_size'],
                    'calculated_at' => $public['last_calculated_at'],
                    'can_recalculate' => in_array($plan['status'], ['new', 'changed', 'withdraw'], true),
                ];
            }
        }
        $order = array_flip(self::STATES);
        usort($rows, static fn(array $a, array $b): int => [$order[$a['state']], $a['name']] <=> [$order[$b['state']], $b['name']]);
        return ['counts' => $counts, 'rows' => $rows];
    }

    /** one model: the public snapshot, what a calculation now would give, and the listings behind it. Null when the model does not exist. */
    public function detail(int $productId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, full_name, slug, is_active FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT pi.* FROM price_indices pi WHERE pi.id = " . PublicSnapshot::idSql(':p'));
        $stmt->execute(['p' => $productId]);
        $public = $stmt->fetch() ?: null;
        $plan = (new SnapshotRecompute($this->db, $this->snapshots))->planProduct($productId, 'asking', (string)$product['full_name']);
        $cohort = $this->snapshots->cohort($productId);
        $reasons = [];
        foreach ($cohort['excluded'] as $row) {
            $reasons[(string)$row['exclusion_reason']] = ($reasons[(string)$row['exclusion_reason']] ?? 0) + 1;
        }
        arsort($reasons);
        return [
            'product' => $product,
            'state' => self::state($public !== null, $plan['status']),
            'public' => $public,
            'preview' => $this->snapshots->previewProduct($productId),
            'can_recalculate' => in_array($plan['status'], ['new', 'changed', 'withdraw'], true),
            'included' => $cohort['included'],
            'excluded_reasons' => $reasons,
        ];
    }

    private static function state(bool $shown, string $planStatus): string
    {
        if ($shown) {
            return $planStatus === 'unchanged' ? 'ok' : 'outdated';
        }
        return match ($planStatus) {
            'new' => 'ready',
            'none' => 'none',
            default => 'hidden',
        };
    }
}
