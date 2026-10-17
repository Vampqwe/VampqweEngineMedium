<?php

declare(strict_types=1);

namespace Core\System\ServiceProvider;

use Core\System\Http\Request;
use Core\System\Http\Response;

/**
 * Провайдер для HTTP-компонентов
 */
class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request — singleton (один запрос на приложение)
        $this->container->singleton(Request::class, fn() =>
            Request::fromGlobals($this->config)
        );

        // Response — фабрика (новый ответ для каждого запроса)
        $this->container->bind(Response::class, fn() =>
            new Response()
        );
    }

    public function boot(): void
    {
        // Ничего не требуется
    }
}