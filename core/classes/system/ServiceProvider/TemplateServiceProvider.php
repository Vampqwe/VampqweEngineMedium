<?php

declare(strict_types=1);

namespace Core\System\ServiceProvider;

use Core\System\Security\SecurityManager;
use Core\System\Template\Template;

class TemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $security = $this->container->get(SecurityManager::class);
        $cspNonce = $security->getCspNonce();

        // Nonce привязан к SecurityManager этого Application, поэтому Template
        // нельзя хранить в статике между повторными инициализациями процесса.
        $this->container->singleton(
            Template::class,
            fn() => new Template($this->config, $cspNonce)
        );
    }

    public function boot(): void
    {
        /** @var Template $template */
        $template = $this->container->get(Template::class);

        $template->shareMany([
            'app_name'  => $this->config->get('APP_NAME', 'Достойный уход'),
            'base_url'  => rtrim($this->config->get('APP_BASE_URL', ''), '/'),
            'config'    => $this->config,
        ]);
    }
}