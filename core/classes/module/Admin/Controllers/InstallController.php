<?php
declare(strict_types=1);

/**
 * VampqweEngineMedium — проприетарный PHP-движок
 *
 * @copyright  Copyright (c) 2024-2026. Все права защищены.
 * @author     [Дедюля Александр Иванович / VampqweEngine]
 * @license    Proprietary
 * @link       https://github.com/Vampqwe/VampqweEngine.git
 * @email	   doktor_try@mail.ru
 *
 * Данный код является интеллектуальной собственностью автора.
 * Любое копирование, распространение, модификация или использование
 * без письменного разрешения правообладателя строго запрещено.
 *
 * This software is proprietary and confidential.
 * Unauthorized copying, distribution, or use is strictly prohibited.
 */


namespace Core\Module\Admin\Controllers;

use Core\Module\Admin\Services\InstallService;
use Core\Module\Admin\Services\AdminService;
use Core\System\Application\InstalledGuard;
use Core\System\Config\Config;
use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Security\SecurityManager;
use Core\System\Template\Template;

class InstallController
{
    private InstallService $installService;
    private AdminService $adminService;
    private Config $config;
    private Template $template;
    private SecurityManager $security;

    public function __construct(
        InstallService $installService,
        AdminService $adminService,
        Config $config,
        Template $template,
        SecurityManager $security
    ) {
        $this->installService = $installService;
        $this->adminService = $adminService;
        $this->config = $config;
        $this->template = $template;
        $this->security = $security;
    }

    public function step1(Request $request): Response
    {
        // Фолбэк для хостингов за балансировщиком/CDN: если PHP-сессия
        // не устоялась (кука потеряна, разные php-fpm-воркеры и т.п.),
        // конфиг БД может быть передан заголовком X-Install-Db из CLI-
        // утилиты установки. Заголовок читается ДО любых изменений —
        // это идемпотентный «заякоривающий» GET (см. middleware).
        if (!$request->isPost()) {
            $headerPayload = trim((string) $request->getHeader('X-Install-Db', ''));
            if ($headerPayload !== '' && !isset($_SESSION['install_db_enc'])) {
                $decoded = json_decode(base64_decode($headerPayload, true) ?: '', true);
                if (is_array($decoded)) {
                    $result = $this->installService->testConnection($decoded);
                    if ($result['success']) {
                        $_SESSION['install_db_enc'] = $this->installService->encryptDbConfig($result['config']);
                        $this->adminService->setFlash('info', 'Конфигурация БД получена из заголовка X-Install-Db.');
                    } else {
                        $this->adminService->setFlash('error', 'X-Install-Db: ' . $result['message']);
                    }
                } else {
                    $this->adminService->setFlash('error', 'Не удалось распознать содержимое заголовка X-Install-Db.');
                }
            }
        }

        if ($request->isPost()) {
            $result = $this->installService->testConnection([
                'host' => $request->input('db_host', '127.0.0.1'),
                'port' => $request->input('db_port', '3306'),
                'name' => $request->input('db_name', ''),
                'user' => $request->input('db_user', ''),
                'pass' => $request->input('db_pass', ''),
            ]);

            if ($result['success']) {
                // ИСПРАВЛЕНО: конфиг БД (включая пароль) шифруется перед
                // помещением в сессию — раньше хранился в открытом виде.
                $_SESSION['install_db_enc'] = $this->installService->encryptDbConfig($result['config']);
                return Response::redirect('/admin/install/step2');
            }

            $this->adminService->setFlash('error', $result['message']);
        }

        return $this->render('admin/install/step1-check', 'Установка — Шаг 1');
    }

    public function step2(Request $request): Response
    {
        if ($request->isPost()) {
            $dbConfig = $this->requireDbConfig();
            if ($dbConfig === null) {
                $this->adminService->setFlash('error', 'Данные подключения к БД не найдены. Начните установку с шага 1.');
                return Response::redirect('/admin/install');
            }

            $result = $this->installService->createTables($dbConfig);

            if ($result['success']) {
                return Response::redirect('/admin/install/step3');
            }

            $this->adminService->setFlash('error', $result['message']);
        }

        return $this->render('admin/install/step2-structure', 'Установка — Шаг 2');
    }

    public function step3(Request $request): Response
    {
        if ($request->isPost()) {
            $dbConfig = $this->requireDbConfig();
            if ($dbConfig === null) {
                $this->adminService->setFlash('error', 'Данные подключения к БД не найдены. Начните установку с шага 1.');
                return Response::redirect('/admin/install');
            }

            $result = $this->installService->seedInitialData($dbConfig);

            if ($result['success']) {
                return Response::redirect('/admin/install/step4');
            }

            $this->adminService->setFlash('error', $result['message']);
        }

        return $this->render('admin/install/step3-data', 'Установка — Шаг 3');
    }

    public function step4(Request $request): Response
    {
        if ($request->isPost()) {
            $dbConfig = $this->requireDbConfig();
            if ($dbConfig === null) {
                $this->adminService->setFlash('error', 'Данные подключения к БД не найдены. Начните установку с шага 1.');
                return Response::redirect('/admin/install');
            }

            $baseUrl   = trim((string)$request->input('base_url', ''));
            $adminMail = trim((string)$request->input('admin_email', ''));
            $adminPass = (string)$request->input('admin_password', '');
            $adminName = trim((string)$request->input('admin_name', ''));

            // Валидация базового URL и учётных данных до записи куда-либо.
            if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                $this->adminService->setFlash('error', 'Укажите корректный базовый URL (например, https://example.com).');
                return $this->render('admin/install/step4-config', 'Установка — Шаг 4');
            }
            if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
                $this->adminService->setFlash('error', 'Укажите корректный email администратора.');
                return $this->render('admin/install/step4-config', 'Установка — Шаг 4');
            }
            if (strlen($adminPass) < 8) {
                $this->adminService->setFlash('error', 'Пароль администратора должен быть не короче 8 символов.');
                return $this->render('admin/install/step4-config', 'Установка — Шаг 4');
            }

            // 1. Учётная запись администратора в БД (только bcrypt-хэш).
            try {
                $pdo = $this->installService->getPdoForInstall($dbConfig);
            } catch (Throwable $e) {
                $this->adminService->setFlash('error', 'Не удалось подключиться к БД: ' . $e->getMessage());
                return $this->render('admin/install/step4-config', 'Установка — Шаг 4');
            }

            $userResult = $this->installService->createAdminUser($pdo, $adminMail, $adminPass, $adminName);
            if (!$userResult['success']) {
                $this->adminService->setFlash('error', $userResult['message']);
                return $this->render('admin/install/step4-config', 'Установка — Шаг 4');
            }

            // 2. Финализация config.env: APP_BASE_URL + SUPER_ADMIN_* (пароль — только хэш).
            $configResult = $this->installService->writeConfigValues([
                'APP_BASE_URL'         => rtrim($baseUrl, '/'),
                'SUPER_ADMIN_EMAIL'    => strtolower($adminMail),
                'SUPER_ADMIN_NAME'     => $adminName !== '' ? $adminName : 'Администратор',
                'SUPER_ADMIN_PASSWORD' => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]),
            ]);
            if (!$configResult['success']) {
                $this->adminService->setFlash('error', $configResult['message']);
                return $this->render('admin/install/step4-config', 'Установка — Шаг 4');
            }

            $dbConfig['base_url'] = rtrim($baseUrl, '/');
            $_SESSION['install_db_enc'] = $this->installService->encryptDbConfig($dbConfig);
            $this->adminService->setFlash('success', 'Конфигурация сохранена, администратор создан. Подтвердите завершение установки.');
            return Response::redirect('/admin/install/step5');
        }

        return $this->render('admin/install/step4-config', 'Установка — Шаг 4', [
            'base_url'    => (string)$this->config->get('APP_BASE_URL', ''),
            'admin_email' => (string)$this->config->get('SUPER_ADMIN_EMAIL', ''),
            'admin_name'  => (string)$this->config->get('SUPER_ADMIN_NAME', ''),
        ]);
    }

    public function step5(Request $request): Response
    {
        // ИСПРАВЛЕНО (HTTP-семантика + идемпотентность): шаг 5 больше не
        // выполняет изменяющие действия на GET. Финализация выполняется
        // POST-запросом; повторный GET просто показывает результат.
        $dbConfig = $this->requireDbConfig();

        if ($request->isPost()) {
            if ($dbConfig === null) {
                $this->adminService->setFlash('error', 'Данные подключения к БД не найдены. Начните установку с шага 1.');
                return Response::redirect('/admin/install');
            }

            // Финализация: сначала приводим config.env к политике MySQL
            // (вырезаем унаследованные sqlite-параметры), и только затем
            // ставим метку .installed — чтобы движок после установки гарантированно
            // работал с боевым MySQL, а не с зарезервированным sqlite-конфигом.
            $policy = $this->installService->enforceMysqlConfigPolicy();
            if ($policy['changed']) {
                $this->adminService->setFlash('info', (string)$policy['message']);
            } elseif (!empty($policy['message']) && !str_contains((string)$policy['message'], 'уже соблюдена')) {
                // Конфиг не найден / нет прав на запись — предупреждаем, но не блокируем установку.
                $this->adminService->setFlash('warning', (string)$policy['message']);
            }

            $this->installService->markAsInstalled($dbConfig);
            unset($_SESSION['install_db_enc']);
            // Сбрасываем per-request кэш сторожа установки, чтобы следующий
            // запрос (редирект на сайт) уже видел движок установленным.
            InstalledGuard::resetCache();
            $finished = true;
        } else {
            // GET: показываем либо экран подтверждения (если установка ещё
            // не финализирована), либо готовую страницу результата.
            $finished = $this->installService->isInstalled();
        }

        return $this->render('admin/install/step5-done', 'Установка — Шаг 5', [
            'install_finished' => $finished,
        ]);
    }

    /**
     * Расшифровать конфиг БД из сессии.
     *
     * @return array<string, string>|null
     */
    private function requireDbConfig(): ?array
    {
        $payload = $_SESSION['install_db_enc'] ?? null;
        if (!is_string($payload)) {
            return null;
        }

        $dbConfig = $this->installService->decryptDbConfig($payload);
        if ($dbConfig === null) {
            unset($_SESSION['install_db_enc']);
        }

        return $dbConfig;
    }

    private function render(string $view, string $title, array $extra = []): Response
    {
        $flash = $this->adminService->getFlash();
        
        $html = $this->template->render($view, array_merge([
            'title' => $title,
            'flash' => $flash,
            'config' => $this->config,
            'csrf_token' => $this->security->getCsrfToken()->getToken(),
        ], $extra));

        return Response::html($html);
    }
}