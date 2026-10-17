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