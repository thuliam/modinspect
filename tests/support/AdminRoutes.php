<?php
declare(strict_types=1);

/** the controller class that public/index.php routes to this admin action (tests call actions by method name) */
function admin_controller_of(string $method): string
{
    $index = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
    if (!preg_match('/\[(Admin\w*Controller)::class,\'' . preg_quote($method, '/') . '\'\]/', $index, $m)) {
        throw new RuntimeException('No admin route for ' . $method);
    }
    return 'App\\Controllers\\' . $m[1];
}
