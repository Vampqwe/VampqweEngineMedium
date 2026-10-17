<?php
declare(strict_types=1);

namespace Core\System\ServiceProvider;

use Core\System\Http\Router;
use Core\System\Http\Response;
use Core\System\Http\Request;
use Core\Module\Admin\Controllers\InstallController;
use Core\Module\Admin\Controllers\AuthController;
use Core\Module\Admin\Controllers\DashboardController;
use Core\Module\Admin\Services\InstallService;
use Core\Module\Admin\Services\AdminService;
use Core\Module\Admin\Services\SuperAdminAuth;
use Core\Module\Admin\Middleware\AdminMiddleware;
use Core\Module\Admin\Middleware\InstallAccessMiddleware;
use Core\System\Config\Config;
use Core\System\DataBase\DataBase;
use Core\System\Security\SecurityManager;
use Core\System\Template\Template;
use Core\Module\Admin\Controllers\PagesController;
use Core\Module\Admin\Services\PageService;
use Core\System\Security\HtmlSanitizer;
use Core\Module\Admin\Controllers\ImportController;
use Core\Module\Admin\Services\HtmlImportParser;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $db = $this->container->get(DataBase::class);
        $config = $this->config;

        // ИСПРАВЛЕНО: корень приложения берётся из конфига (FS_BASE_PATH),
        // иначе InstallService вычислял путь от __DIR__ и в проде читал
        // storage/.installed, .install_key и .install_seed не из того корня.
        // Нормализация: FS_BASE_PATH может указывать на storage-каталог
        // (исторический формат) — приводим к корню проекта.
        $basePath = (string) ($config->get('FS_BASE_PATH') ?: dirname(__DIR__, 5));
        $basePath = rtrim($basePath, '/\\');
        if (is_dir($basePath) && strtolower(basename($basePath)) === 'storage') {
            $basePath = dirname($basePath);
        } elseif (!is_dir($basePath) || !is_file($basePath . '/index.php')) {
            // Недостоверный путь в конфиге — fallback на корень проекта от __DIR__.
            $basePath = dirname(__DIR__, 5);
        }
        $this->container->singleton(InstallService::class, fn() => 
            new InstallService($config, $basePath)
        );

        $this->container->singleton(AdminService::class, fn() => 
            new AdminService($db)
        );

        $this->container->singleton(SuperAdminAuth::class, fn() => 
            new SuperAdminAuth($config)
        );
		
		$this->container->singleton(PageService::class, fn() => 
			new PageService($this->container->get(DataBase::class))
		);
		
		$this->container->singleton(HtmlSanitizer::class, fn() => new HtmlSanitizer());
		
		$this->container->singleton(HtmlImportParser::class, fn() => 
			new HtmlImportParser($config)
		);
    }

    public function boot(): void
    {
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        /** @var Router $router */
        $router = $this->container->get(Router::class);
        $container = $this->container;

        // Единая фабрика контроллера мастера установки (устраняет бойлерплейт).
        $installController = static fn(): InstallController => new InstallController(
            $container->get(InstallService::class),
            $container->get(AdminService::class),
            $container->get(Config::class),
            $container->get(Template::class),
            $container->get(SecurityManager::class)
        );

        // Критическая защита мастера установки: доступ только по ключу
        // активации (INSTALL_KEY в config.env) либо по сессии администратора,
        // обязательная CSRF-валидация POST и rate limiting.
        $installAccess = new InstallAccessMiddleware(
            $container->get(InstallService::class),
            $container->get(SecurityManager::class)
        );

        // ─── Маршруты установки ───
        $router->group('/admin/install', $installAccess)
            ->get('/', function (Request $request) use ($installController): Response {
                return $installController()->step1($request);
            })->name('admin.install.step1');

        $router->group('/admin/install', $installAccess)
            ->post('/step1', function (Request $request) use ($installController): Response {
                return $installController()->step1($request);
            });

        $router->group('/admin/install', $installAccess)
            ->get('/step2', function (Request $request) use ($installController): Response {
                return $installController()->step2($request);
            });

        $router->group('/admin/install', $installAccess)
            ->post('/step2', function (Request $request) use ($installController): Response {
                return $installController()->step2($request);
            });

        $router->group('/admin/install', $installAccess)
            ->get('/step3', function (Request $request) use ($installController): Response {
                return $installController()->step3($request);
            });

        $router->group('/admin/install', $installAccess)
            ->post('/step3', function (Request $request) use ($installController): Response {
                return $installController()->step3($request);
            });

        $router->group('/admin/install', $installAccess)
            ->get('/step4', function (Request $request) use ($installController): Response {
                return $installController()->step4($request);
            });

        $router->group('/admin/install', $installAccess)
            ->post('/step4', function (Request $request) use ($installController): Response {
                return $installController()->step4($request);
            });

        $router->group('/admin/install', $installAccess)
            ->get('/step5', function (Request $request) use ($installController): Response {
                return $installController()->step5($request);
            });

        // Шаг 5 финализирует установку — только POST (CSRF проверяется middleware).
        $router->group('/admin/install', $installAccess)
            ->post('/step5', function (Request $request) use ($installController): Response {
                return $installController()->step5($request);
            });

        // ─── Публичные маршруты авторизации ───
        $router->get('/admin/login', function (Request $request) use ($container): Response {
            $controller = new AuthController(
                $container->get(SuperAdminAuth::class),
                $container->get(AdminService::class),
                $container->get(Config::class),
                $container->get(DataBase::class),
                $container->get(SecurityManager::class),
                $container->get(Template::class)
            );
            return $controller->showLogin($request);
        })->name('admin.login');

        $router->post('/admin/login', function (Request $request) use ($container): Response {
            $controller = new AuthController(
                $container->get(SuperAdminAuth::class),
                $container->get(AdminService::class),
                $container->get(Config::class),
                $container->get(DataBase::class),
                $container->get(SecurityManager::class),
                $container->get(Template::class)
            );
            return $controller->login($request);
        });

        // ─── Защищённые маршруты ───
        // Передаем InstallService в конструктор AdminMiddleware
        $admin = $router->group('/admin', new AdminMiddleware(
            $container->get(InstallService::class)
        ));

        $admin->get('/', function (Request $request) use ($container): Response {
            $controller = new DashboardController(
                $container->get(AdminService::class),
                $container->get(Template::class)
            );
            return $controller->index($request);
        })->name('admin.dashboard');
		
			// ── CRUD страниц ───
	        // ─── CRUD страниц ───
        $admin->get('/pages', function (Request $request) use ($container): Response {
            $controller = new PagesController(
                $container->get(PageService::class),
                $container->get(AdminService::class),
                $container->get(Template::class)
            );
            return $controller->index($request);
        })->name('admin.pages.index');

        $admin->get('/pages/new', function (Request $request) use ($container): Response {
            $controller = new PagesController(
                $container->get(PageService::class),
                $container->get(AdminService::class),
                $container->get(Template::class)
            );
            return $controller->create($request);
        })->name('admin.pages.create');

        $admin->post('/pages/store', function (Request $request) use ($container): Response {
            $controller = new PagesController(
                $container->get(PageService::class),
                $container->get(AdminService::class),
                $container->get(Template::class)
            );
            return $controller->store($request);
        })->name('admin.pages.store');

        $admin->get('/pages/{id}/edit', function (Request $request) use ($container): Response {
            $controller = new PagesController(
                $container->get(PageService::class),
                $container->get(AdminService::class),
                $container->get(Template::class)
            );
            return $controller->edit($request);
        })->name('admin.pages.edit');

        $admin->post('/pages/{id}/update', function (Request $request) use ($container): Response {
            $controller = new PagesController(
                $container->get(PageService::class),
                $container->get(AdminService::class),
                $container->get(Template::class)
            );
            return $controller->update($request);
        })->name('admin.pages.update');

        $admin->post('/pages/{id}/delete', function (Request $request) use ($container): Response {
            $controller = new PagesController(
                $container->get(PageService::class),
                $container->get(AdminService::class),
                $container->get(Template::class)
            );
            return $controller->destroy($request);
        })->name('admin.pages.delete');

        $admin->post('/logout', function (Request $request) use ($container): Response {
            $controller = new AuthController(
                $container->get(SuperAdminAuth::class),
                $container->get(AdminService::class),
                $container->get(Config::class),
                $container->get(DataBase::class),
                $container->get(SecurityManager::class),
                $container->get(Template::class)
            );
            return $controller->logout($request);
        })->name('admin.logout');
		
		// ─── Импорт страниц ───
		$admin->get('/import', function (Request $request) use ($container): Response {
			$controller = new ImportController(
				$container->get(HtmlImportParser::class),
				$container->get(PageService::class),
				$container->get(AdminService::class),
				$container->get(Template::class),
				$container->get(Config::class)
			);
			return $controller->index($request);
		})->name('admin.import.index');

		$admin->post('/import/upload', function (Request $request) use ($container): Response {
			$controller = new ImportController(
				$container->get(HtmlImportParser::class),
				$container->get(PageService::class),
				$container->get(AdminService::class),
				$container->get(Template::class),
				$container->get(Config::class)
			);
			return $controller->upload($request);
		})->name('admin.import.upload');

		$admin->post('/import/confirm', function (Request $request) use ($container): Response {
			$controller = new ImportController(
				$container->get(HtmlImportParser::class),
				$container->get(PageService::class),
				$container->get(AdminService::class),
				$container->get(Template::class),
				$container->get(Config::class)
			);
			return $controller->confirm($request);
		})->name('admin.import.confirm');
    }
}