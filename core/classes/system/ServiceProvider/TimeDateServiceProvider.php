<?php

declare(strict_types=1);

namespace Core\System\ServiceProvider;

class TimeDateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $timezone = $this->config->get('TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);
    }

    public function boot(): void
    {
        // Ничего не требуется
    }
}