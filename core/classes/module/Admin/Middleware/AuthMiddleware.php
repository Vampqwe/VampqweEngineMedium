<?php
declare(strict_types=1);

namespace Core\Module\Admin\Middleware;

use Core\System\Http\Request;
use Core\System\Http\Response;
use Core\System\Http\Middleware;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!isset($_SESSION['admin_user'])) {
            $_SESSION['redirect_after_login'] = $request->getPath();
            return Response::redirect('/admin/login');
        }

        return $next($request);
    }
}