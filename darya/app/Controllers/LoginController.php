<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;

class LoginController extends Controller
{
    public bool $public = true;

    public function indexAction(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $u = (string) $this->input('username');
            $p = (string) $this->input('password');
            if (Auth::attempt($u, $p)) {
                flash_set('success', 'خوش آمدید 👋');
                $this->redirect($_SESSION['intended'] ?? 'dashboard');
            }
            flash_set('error', 'نام کاربری یا رمز عبور اشتباه است (یا تلاش زیاد؛ ۵ دقیقه صبر کنید).');
        }
        $this->view('auth/login', [], 'auth');
    }

    public function logoutAction(): void
    {
        Auth::logout();
        header('Location: ' . url('login'));
        exit;
    }
}
