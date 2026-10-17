<?php
declare(strict_types=1);

namespace Core\Module\Admin\Middleware;

use Core\Module\Admin\Services\InstallService;
use Core\System\Http\Middleware;
use Core\System\Http\MiddlewareStack;
use Core\System\Http\Request;
use Core\System\Http\Response;

/**
 * Композитный middleware группы /admin.
 *
 * Исправлена критическая ошибка старой реализации: каждый из middleware
 * вызывал $next независимо, в результате финальный обработчик (контроллер)
 * выполнялся ДВАЖДЫ, а результат install-проверки терялся. Теперь цепочка
 * собирается через MiddlewareStack и обрабатывается строго последовательно:
 * InstallMiddleware → AuthMiddleware → AdminCspMiddleware → контроллер.
 */
class AdminMiddleware implements Middleware
{
    private MiddlewareStack $stack;

    public function __construct(InstallService $installService)
    {
        $this->stack = (new MiddlewareStack())
            ->push(new InstallMiddleware($installService))
            ->push(new AuthMiddleware())
            ->push(new AdminCspMiddleware());
    }

    public function handle(Request $request, callable $next): Response
    {
        return $this->stack->process($request, $next);
    }
}