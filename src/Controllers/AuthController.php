<?php

namespace App\Controllers;

use App\Database\Database;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['admin_logged_in'])) {
            $this->redirect('/admin');
            return;
        }

        $this->render('login.twig', [
            'page_title' => 'Admin Login',
        ]);
    }

    /**
     * Process login
     */
    public function login(): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/admin/login');
            return;
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] = 'Username and password are required';
            $this->redirect('/admin/login');
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM admin_users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];

            // Update last login
            $stmt = $db->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?');
            $stmt->execute([$user['id']]);

            $this->redirect('/admin');
            return;
        }

        $_SESSION['login_error'] = 'Invalid username or password';
        $this->redirect('/admin/login');
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect('/admin/login');
    }
}