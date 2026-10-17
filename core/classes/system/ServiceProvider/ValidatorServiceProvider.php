<?php

declare(strict_types=1);

namespace Core\System\ServiceProvider;

class ValidatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Validator создаётся по требованию через Validator::make()
    }

    public function boot(): void
    {
        // Ничего не требуется
    }
}