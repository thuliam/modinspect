<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\Auth\AuthService;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if ((new AuthService())->currentUser()) {
            $this->redirect('/admin');
        }
        $this->view('auth/login', ['title' => 'เข้าสู่ระบบ Admin', 'error' => null]);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']);
            return;
        }
        $result = (new AuthService())->login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? null);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(401);
            $this->view('auth/login', ['title' => 'เข้าสู่ระบบ Admin', 'error' => $result['error'] === 'TOO_MANY_ATTEMPTS' ? 'ลองใหม่ภายหลัง' : 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);
            return;
        }
        $returnTo = (string)($_SESSION['auth_return_to'] ?? '/admin');
        unset($_SESSION['auth_return_to']);
        $this->redirect(str_starts_with($returnTo, '/admin') ? $returnTo : '/admin');
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']);
            return;
        }
        (new AuthService())->logout(true);
        $this->redirect('/login');
    }
}
