<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Two-language text for the admin: Thai is the default and the fallback, English is the choice. Texts live in app/Lang/th.php and app/Lang/en.php
 * (flat key => text arrays; "{name}" is replaced from the params). The language is a viewer's convenience kept in the session and a plain cookie; it is never
 * read from a URL, so a link cannot switch someone's language. A missing key shows the Thai text, and a key missing everywhere shows the key itself
 * (tests/lang_test.php keeps the two files in step and checks every key used in the views exists).
 */
final class Lang
{
    public const SUPPORTED = ['th', 'en'];
    public const DEFAULT = 'th';
    public const COOKIE = 'mi_lang';

    /** @var array<string,array<string,string>> */
    private static array $cache = [];

    public static function current(): string
    {
        $s = $_SESSION['lang'] ?? null;
        if (is_string($s) && in_array($s, self::SUPPORTED, true)) { return $s; }
        $c = $_COOKIE[self::COOKIE] ?? null;
        return is_string($c) && in_array($c, self::SUPPORTED, true) ? $c : self::DEFAULT;
    }

    public static function set(string $lang): bool
    {
        if (!in_array($lang, self::SUPPORTED, true)) { return false; }
        $_SESSION['lang'] = $lang;
        return true;
    }

    /** @param array<string,scalar> $params */
    public static function t(string $key, array $params = [], ?string $lang = null): string
    {
        $lang = $lang !== null && in_array($lang, self::SUPPORTED, true) ? $lang : self::current();
        $text = self::load($lang)[$key] ?? self::load(self::DEFAULT)[$key] ?? $key;
        foreach ($params as $k => $v) { $text = str_replace('{' . $k . '}', (string)$v, $text); }
        return $text;
    }

    /** @return array<string,string> */
    public static function all(string $lang): array { return self::load($lang); }

    /** "New-price refs" -> "new_price_refs": the key a menu label is looked up under */
    public static function slug(string $label): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
    }

    /** a back address that is a plain local path, or null */
    public static function safeBack(mixed $path): ?string
    {
        if (!is_string($path) || $path === '' || strlen($path) > 300 || $path[0] !== '/' || str_starts_with($path, '//') || preg_match('/[\x00-\x1F\x7F\\\\]|:\/\//', $path)) { return null; }
        return $path;
    }

    /** @return array<string,string> */
    private static function load(string $lang): array
    {
        if (!isset(self::$cache[$lang])) {
            $file = dirname(__DIR__) . '/Lang/' . $lang . '.php';
            $data = is_file($file) ? require $file : [];
            self::$cache[$lang] = is_array($data) ? $data : [];
        }
        return self::$cache[$lang];
    }
}
