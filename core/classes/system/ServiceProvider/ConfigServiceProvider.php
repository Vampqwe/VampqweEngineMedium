<?php

declare(strict_types=1);

namespace Core\System\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config уже зарегистрирован в Application::registerConfig()
    }

    public function boot(): void
    {
        // Можно выполнить валидацию конфига
    }
}