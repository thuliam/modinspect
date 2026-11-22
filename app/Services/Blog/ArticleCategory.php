<?php
declare(strict_types=1);

namespace App\Services\Blog;

use PDO;

/** LS-55: the categories an article can carry (the catalog's eight, or none = general). Pure, plus a cached check that the column exists (a database that has not applied migration 030 keeps working without categories). */
final class ArticleCategory
{
    public const LABELS = ['cpu' => 'CPU', 'gpu' => 'GPU', 'motherboard' => 'Motherboard', 'ram' => 'RAM', 'storage' => 'Storage', 'psu' => 'PSU', 'case' => 'Case', 'cooling' => 'Cooling'];
    public const GENERAL = 'general';
    public const ICONS = ['cpu' => 'fa-microchip', 'gpu' => 'fa-tv', 'motherboard' => 'fa-project-diagram', 'ram' => 'fa-memory', 'storage' => 'fa-hdd', 'psu' => 'fa-plug', 'case' => 'fa-server', 'cooling' => 'fa-fan', 'general' => 'fa-book-open'];

    private static ?bool $hasColumn = null;

    /** a stored or submitted value as a known slug, otherwise null (general) */
    public static function normalize(mixed $v): ?string
    {
        $v = is_string($v) ? strtolower(trim($v)) : '';
        return isset(self::LABELS[$v]) ? $v : null;
    }

    /** a query value for filtering: a known slug, "general", or null for all */
    public static function filter(mixed $v): ?string
    {
        $v = is_string($v) ? strtolower(trim($v)) : '';
        return isset(self::LABELS[$v]) || $v === self::GENERAL ? $v : null;
    }

    public static function label(?string $slug): string
    {
        return $slug !== null && isset(self::LABELS[$slug]) ? self::LABELS[$slug] : 'ทั่วไป';
    }

    public static function columnExists(PDO $db): bool
    {
        if (self::$hasColumn === null) {
            self::$hasColumn = (bool) $db->query("SHOW COLUMNS FROM articles LIKE 'category'")->fetch();
        }
        return self::$hasColumn;
    }
}
