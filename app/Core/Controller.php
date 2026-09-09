<?php
declare(strict_types=1);
namespace App\Core;

class Controller
{
    public function view(string $view, array $data = []): void {
        global $config;
        extract($data, EXTR_SKIP);
        $viewFile = ROOT_PATH . '/app/Views/' . $view . '.php';
        require ROOT_PATH . '/app/Views/layouts/header.php';
        require $viewFile;
        require ROOT_PATH . '/app/Views/layouts/footer.php';
    }
    protected function redirect(string $path): never {
        global $config;
        if (PHP_SAPI === 'cli' && defined('MODINSPECT_TESTING')) {
            throw new RedirectException($path);
        }
        header('Location: ' . $config['base_url'] . $path);
        exit;
    }
}
