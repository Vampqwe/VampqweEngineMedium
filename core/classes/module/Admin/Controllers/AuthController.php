<?php
declare(strict_types=1);

namespace Core\Module\Admin\Controllers;

use Core\Module\Admin\Services\SuperAdminAuth;
use Core\Module\Admin\Services\AdminService;
use Core\System\Config\Config;
use Core\System\DataBase\DataBase;
use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Http\StatusCode;
use Core\System\Security\SecurityManager;
use Core\System\Template\Template;

class AuthController
{
    private SuperAdminAuth $superAdminAuth;
    private AdminService $adminService;
    private Config $config;
    private DataBase $db;
    private SecurityManager $security;
    private Template $template;

    public function __construct(
        SuperAdminAuth $superAdminAuth,
        AdminService $adminService,
        Config $config,
        DataBase $db,
        SecurityManager $security,
        Template $template
    ) {
        $this->superAdminAuth = $superAdminAuth;
        $this->adminService = $adminService;
        $this->config = $config;
        $this->db = $db;
        $this->security = $security;
        $this->template = $template;
    }

    /**
     * Показать форму входа
     */
    public function showLogin(Request $request): Response
    {
        // Если уже авторизован — редирект на дашборд
        if (isset($_SESSION['admin_user'])) {
            return Response::redirect('/admin/');
        }

        $flash = $this->adminService->getFlash();

        $html = $this->template->render('admin/auth/login', [
            'title' => 'Вход в админ-панель',
            'flash' => $flash,
            'csrf_token' => $this->security->getCsrfToken()->getToken(),
        ]);

        return Response::html($html, StatusCode::OK)
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Обработка входа
     */
    public function login(Request $request): Response
    {
        // Rate limit: защита от брутфорса
        $ip = $request->getIp();
        $rateLimit = $this->security->attempt('admin:login:' . $ip);
        
        if (!$rateLimit['allowed']) {
            $this->adminService->setFlash('error', 'Слишком много попыток входа. Попробуйте позже.');
            return Response::redirect('/admin/login');
        }

        // CSRF проверка
        try {
            $this->security->validateCsrf($request->input('_csrf_token', ''));
        } catch (\Exception $e) {
            $this->adminService->setFlash('error', 'Недействительный CSRF-токен');
            return Response::redirect('/admin/login');
        }

        // ИСПРАВЛЕНО: используем input() вместо getPost()
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (empty($email) || empty($password)) {
            $this->adminService->setFlash('error', 'Введите email и пароль');
            return Response::redirect('/admin/login');
        }

        // 1. Проверяем супер-админа (из config.env)
        if ($this->superAdminAuth->isSuperAdmin($email, $password)) {
            $_SESSION['admin_user'] = $this->superAdminAuth->getSuperAdminData();
            $this->adminService->setFlash('success', 'Добро пожаловать, Супер Администратор!');
            return Response::redirect('/admin/');
        }

        // 2. Проверяем обычного пользователя (из БД)
        $user = $this->db->fetchOne(
            "SELECT id, email, name, role, password_hash FROM users WHERE email = :email AND is_active = 1",
            ['email' => $email]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'is_super' => false,
            ];

            // Обновляем last_login_at
            $this->db->execute(
                "UPDATE users SET last_login_at = NOW() WHERE id = :id",
                ['id' => $user['id']]
            );

            $this->adminService->setFlash('success', "Добро пожаловать, {$user['name']}!");
            return Response::redirect('/admin/');
        }

        // 3. Неверные данные
        $this->adminService->setFlash('error', 'Неверный email или пароль');
        return Response::redirect('/admin/login');
    }

    /**
     * Выход из системы
     */
    public function logout(Request $request): Response
    {
        unset($_SESSION['admin_user']);
        $this->adminService->setFlash('success', 'Вы вышли из системы');
        return Response::redirect('/admin/login');
    }
}