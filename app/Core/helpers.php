<?php
declare(strict_types=1);

/** short name for views: t('nav.products'). See App\Core\Lang. */
if (!function_exists('t')) {
    /** @param array<string,scalar> $params */
    function t(string $key, array $params = []): string { return \App\Core\Lang::t($key, $params); }
}
