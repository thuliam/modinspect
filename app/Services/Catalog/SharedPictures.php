<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-90 T4: one photograph file stored under the names of two or more models shows a wrong product on at least one of them,
 * and nothing says which is right. Such a picture is not drawn (the category artwork is used instead). No file is removed:
 * replacing one of the copies with the right photograph brings it back.
 */
final class SharedPictures
{
    /** @var array<string,array<string,true>> directory => file names that have a byte-identical twin */
    private static array $cache = [];

    /** whether this {slug}.jpg has the same bytes as the {slug}.jpg of another model in the same folder */
    public static function isShared(string $file): bool
    {
        $dir = dirname($file);
        return isset((self::$cache[$dir] ??= self::scan($dir))[basename($file)]);
    }

    /** @return array<string,true> */
    private static function scan(string $dir): array
    {
        $bySize = [];
        foreach (glob($dir . '/*.jpg') ?: [] as $path) {
            // only the files a model page can draw: {slug}.jpg
            if (preg_match('/^[a-z0-9-]+\.jpg$/', basename($path)) === 1) {
                $bySize[(int)filesize($path)][] = $path;
            }
        }
        $shared = [];
        foreach ($bySize as $paths) {
            if (count($paths) < 2) {
                continue;   // a different size cannot be the same file, so most pictures are never read
            }
            $byHash = [];
            foreach ($paths as $path) {
                $byHash[(string)sha1_file($path)][] = basename($path);
            }
            foreach ($byHash as $names) {
                if (count($names) > 1) {
                    foreach ($names as $name) {
                        $shared[$name] = true;
                    }
                }
            }
        }
        return $shared;
    }
}
