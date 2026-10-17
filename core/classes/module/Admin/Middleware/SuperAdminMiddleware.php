<?php
declare(strict_types=1);

namespace Core\Module\Admin\Middleware;

use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Http\StatusCode;

/**
 * Проверяет, что пользователь является супер-админом
 * Используется для маршрутов, доступных только супер-админу
 */
class SuperAdminMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (!isset($_SESSION['admin_user'])) {
            return Response::redirect('/admin/login');
        }

        if (!($_SESSION['admin_user']['is_super'] ?? false)) {
            return Response::text('Доступ запрещён. Требуется роль супер-администратора.', StatusCode::FORBIDDEN);
        }

        return $next($request);
    }
}