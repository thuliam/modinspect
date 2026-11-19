<?php
declare(strict_types=1);

namespace App\Services\Intake;

use App\Services\Admin\IntakeTargets;
use DateTimeImmutable;
use PDO;

/**
 * LS-23: the catalog facts the intake check needs, read once (SELECTs only): which models exist, which aliases are taken, which models still need a new price, and a
 * resolver from a listing title to a catalog model. Shared by cli/intake_validate.php and the daily run.
 */
final class IntakeContext
{
    /** @return array<string,mixed> keys: now, products (normName => category), names (normName => full name), aliases, b_targets, resolve */
    public static function fromDb(PDO $db, ?DateTimeImmutable $now = null): array
    {
        $ctx = ['now' => $now ?? new DateTimeImmutable('now'), 'products' => [], 'names' => [], 'aliases' => []];
        foreach ($db->query('SELECT p.full_name n, c.slug cat FROM products p JOIN product_categories c ON c.id=p.category_id WHERE p.is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = IntakeValidator::norm((string)$r['n']); $ctx['products'][$k] = (string)$r['cat']; $ctx['names'][$k] = (string)$r['n'];
        }
        foreach ($db->query('SELECT normalized_alias a FROM product_aliases')->fetchAll(PDO::FETCH_COLUMN) as $a) { $ctx['aliases'][(string)$a] = true; }
        $ctx['b_targets'] = array_map(static fn(array $t): string => $t[2], IntakeTargets::newPriceTargets($db));
        $resolver = new \App\Services\ProductResolver\ProductResolver((new \App\Models\CollectionPipeline())->catalog());
        $ctx['resolve'] = static function (string $title) use ($resolver): ?string {
            $r = $resolver->resolve($title);
            return $r->productId !== null && $r->confidence >= 0.7 ? $r->matchedName : null;
        };
        $ctx['resolver'] = $resolver;
        return $ctx;
    }
}
