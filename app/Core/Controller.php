<?php
declare(strict_types=1);
namespace App\Core;

class Controller
{
    public function view(string $view, array $data = []): void {
        global $config;
        extract($data, EXTR_SKIP);
        $viewFile = ROOT_PATH . '/app/Views/' . $view . '.php';
        if (str_starts_with($view, 'admin/')) {
            require ROOT_PATH . '/app/Views/layouts/admin_header.php';
            require $viewFile;
            require ROOT_PATH . '/app/Views/layouts/admin_footer.php';
            return;
        }
        if (str_starts_with($view, 'auth/')) {
            require ROOT_PATH . '/app/Views/layouts/auth_header.php';
            require $viewFile;
            require ROOT_PATH . '/app/Views/layouts/auth_footer.php';
            return;
        }
        require ROOT_PATH . '/app/Views/layouts/header.php';
        require $viewFile;
        require ROOT_PATH . '/app/Views/layouts/footer.php';
    }
    /** LS-17 A2 (S-3): a query value as a string; "?q[]=x" arrives as an array and is treated as absent instead of raising "Array to string" warnings */
    protected static function queryString(string $key): string {
        $v = $_GET[$key] ?? '';
        return is_string($v) ? $v : '';
    }
    protected function redirect(string $path): never {
        global $config;
        if (defined('MODINSPECT_TESTING')) {
            throw new RedirectException($path);
        }
        header('Location: ' . $config['base_url'] . $path);
        exit;
    }
}
