<?php

declare(strict_types=1);

namespace Core\System\ServiceProvider;

use Core\System\Logger\Logger;

class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Logger::class, fn() =>
            new Logger($this->config)
        );
    }

    public function boot(): void
    {
        if ($this->config->getBool('LOG_ENABLED', true)) {
            $logger = $this->container->get(Logger::class);
            $logger->info('Приложение запущено', [
                'base_path' => $this->app->getBasePath(),
                'php'       => PHP_VERSION,
            ]);
        }
    }
}