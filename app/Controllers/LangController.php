<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Lang;

/** Switches the admin language (TH/EN). POST only, CSRF checked, and the way back can only be a local path. */
final class LangController extends Controller
{
    public function set(): void
    {
        $back = Lang::safeBack($_POST['back'] ?? null) ?? '/admin';
        if (Csrf::verify($_POST['_token'] ?? null) && is_string($_POST['lang'] ?? null) && Lang::set($_POST['lang'])) {
            if (!defined('MODINSPECT_TESTING') && !headers_sent()) {
                setcookie(Lang::COOKIE, $_POST['lang'], ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
            }
        }
        $this->redirect($back);
    }
}
