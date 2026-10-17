<?php
declare(strict_types=1);

namespace Core\System\ServiceProvider;

use Core\System\Security\HtmlSanitizer;

class HtmlSanitizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Регистрируем HtmlSanitizer как singleton в контейнере
        $this->container->singleton(HtmlSanitizer::class, fn() => new HtmlSanitizer());
    }

    public function boot(): void
    {
        // Ничего не нужно инициализировать
    }
}