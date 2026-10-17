<?php
declare(strict_types=1);

namespace Core\Module\Admin\Middleware;

use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Http\Middleware;

/**
 * Middleware для ослабления CSP в админ-панели.
 * Разрешает inline-стили и скрипты для работы WYSIWYG-редакторов.
 */
class AdminCspMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        // Переопределяем CSP для админки
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline'; "
             . "style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data: blob:; "
             . "font-src 'self' data:;";

        $response->setHeader('Content-Security-Policy', $csp);

        return $response;
    }
}