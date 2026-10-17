<?php
declare(strict_types=1);

namespace Core\Module\Admin\Middleware;

use Core\Module\Admin\Services\InstallService;
use Core\System\Http\Middleware;
use Core\System\Http\Request;
use Core\System\Http\Response;

/**
 * Middleware мастера установки (защита от повторной установки).
 *
 * Порядок выполнения в группе /admin: InstallAccessMiddleware →
 * InstallMiddleware → AuthMiddleware. Сначала доступ к мастеру
 * проверяется по ключу активации/сессии администратора, затем здесь —
 * корректность перехода между шагами.
 */
class InstallMiddleware implements Middleware
{
    public function __construct(private readonly InstallService $installService)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        // Полный признак (метка + схема БД через InstalledGuard): если метка
        // есть, а базы нет — это НЕ «установлено», мастер должен быть доступен
        // для починки/переустановки.
        $isInstalled = $this->installService->isFullyInstalled();
        $isInstallRoute = str_starts_with($request->getPath(), '/admin/install');

        // Если сайт НЕ установлен и мы НЕ на маршрутах установки → редирект на установку
        if (!$isInstalled && !$isInstallRoute) {
            return Response::redirect('/admin/install');
        }

        // Если сайт УЖЕ установлен и GET-запрос к /admin/install → редирект на дашборд.
        // POST-запросы к установленному сайту блокирует InstallAccessMiddleware (403).
        if ($isInstalled && $isInstallRoute && $request->isGet()) {
            return Response::redirect('/admin/');
        }

        return $next($request);
    }
}
