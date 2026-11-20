<?php
declare(strict_types=1);

namespace App\Services\Intake;

/**
 * LS-23 B6: datasets D (new models and the names people use for them) as an additive, idempotent SQL migration. Pure: returns text, never touches a database.
 * Product Master stays separate from observations: a new model enters with no price, no observation and no snapshot. Statements only INSERT; a model or alias that
 * already exists is skipped by NOT EXISTS, never changed. Variants are never merged: an alias that normalises to the same text as another model's name or alias
 * in the batch is dropped and reported, and a bare number alias ("3070") is reported as risky because it could stand for a Ti or Super variant.
 */
final class IntakeProductsMigration
{
    public static function q(string $s): string { return "'" . str_replace(['\\', "'", "\0"], ['\\\\', "''", ''], $s) . "'"; }
    public static function slug(string $s): string { return trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-'); }

    /**
     * @param list<array<string,string>> $products valid rows of D_new_products.csv
     * @param list<array<string,string>> $aliases   valid rows of D_aliases.csv
     * @return array{sql:string,models:int,aliases:int,dropped:list<array{alias:string,reason:string}>,risky:list<string>}
     */
    public static function generate(array $products, array $aliases): array
    {
        $out = []; $dropped = []; $risky = []; $owner = []; $done = []; $nAliases = 0;
        foreach (array_values(array_unique(array_map(static fn(array $p): string => trim($p['brand']), $products))) as $brand) {
            $out[] = 'INSERT IGNORE INTO brands (name,slug) VALUES (' . self::q($brand) . ',' . self::q(self::slug($brand)) . ');';
        }
        // who owns each normalised name inside the batch
        $claim = static function (string $norm, string $product) use (&$owner): bool {
            if ($norm === '') { return false; }
            if (isset($owner[$norm]) && $owner[$norm] !== $product) { return false; }
            $owner[$norm] = $product; return true;
        };
        foreach ($products as $p) {
            $full = trim($p['full_name']); $model = trim($p['model_name']);
            $out[] = 'INSERT INTO products (category_id,brand_id,model_name,slug,full_name,generation,release_year,spec_summary,tdp_watt,recommended_psu_watt,socket,chipset,memory_type,form_factor,capacity_gb,image_path,is_popular,is_active,created_at,updated_at)'
                . ' SELECT c.id,b.id,' . self::q($model) . ',' . self::q(self::slug($full)) . ',' . self::q($full) . ','
                . (trim($p['generation'] ?? '') === '' ? 'NULL' : self::q(trim($p['generation']))) . ',' . (trim($p['release_year'] ?? '') === '' ? 'NULL' : (string)(int)$p['release_year'])
                . ',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,1,NOW(),NOW()'
                . ' FROM product_categories c JOIN brands b ON b.name=' . self::q(trim($p['brand'])) . ' WHERE c.slug=' . self::q(trim($p['category']))
                . ' AND NOT EXISTS (SELECT 1 FROM products WHERE slug=' . self::q(self::slug($full)) . ' OR full_name=' . self::q($full) . ');';
            foreach ([[$model, 100], [$full, 100]] as [$a, $conf]) {
                $n = IntakeValidator::norm($a);
                if ($claim($n, $full) && !isset($done[$n])) { $done[$n] = true; $out[] = self::aliasSql($full, $a, $n, $conf); $nAliases++; }
            }
        }
        foreach ($aliases as $a) {
            $text = trim($a['alias_text']); $n = IntakeValidator::norm($text); $prod = trim($a['product_name']);
            if (isset($done[$n])) { if (($owner[$n] ?? $prod) !== $prod) { $dropped[] = ['alias' => $text, 'reason' => 'SAME_NAME_AS_ANOTHER_MODEL']; } continue; }
            if (!$claim($n, $prod)) { $dropped[] = ['alias' => $text, 'reason' => 'SAME_NAME_AS_ANOTHER_MODEL']; continue; }
            if (preg_match('/^\d{3,5}$/', $n)) { $risky[] = $text; }
            $done[$n] = true;
            $out[] = self::aliasSql($prod, $text, $n, ($a['alias_kind'] ?? '') === 'typo' ? 90 : 95);
            $nAliases++;
        }
        $head = "-- LS-23 B6: new catalog models and aliases from a validated collection batch. Additive and idempotent: INSERT only, existing models and aliases are skipped (NOT EXISTS).\n"
            . "-- Product Master only: the models enter with no price, no observation and no snapshot; specs arrive through the reference-spec migration.\n"
            . '-- ' . count($products) . ' models, ' . $nAliases . ' aliases, ' . count($dropped) . " aliases dropped (same normalised name as another model), " . count($risky) . " bare-number aliases flagged.\n\n";
        return ['sql' => $head . implode("\n", $out) . "\n", 'models' => count($products), 'aliases' => $nAliases, 'dropped' => $dropped, 'risky' => $risky];
    }

    private static function aliasSql(string $productFullName, string $alias, string $norm, int $confidence): string
    {
        return 'INSERT INTO product_aliases (product_id,alias_text,normalized_alias,confidence) SELECT id,' . self::q($alias) . ',' . self::q($norm) . ',' . $confidence
            . ' FROM products WHERE full_name=' . self::q($productFullName) . ' AND NOT EXISTS (SELECT 1 FROM product_aliases WHERE normalized_alias=' . self::q($norm) . ') ORDER BY id LIMIT 1;';
    }
}
