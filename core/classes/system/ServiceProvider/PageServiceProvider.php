<?php

declare(strict_types=1);

namespace Core\System\ServiceProvider;

use Core\Module\Page\MenuService;
use Core\Module\Page\PageService;
use Core\Module\Page\RedirectService;
use Core\Module\Page\SitemapService;

/**
 * Провайдер для страниц, меню и редиректов
 */
class PageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $db = $this->container->get(\Core\System\DataBase\DataBase::class);

        $this->container->singleton(PageService::class, fn() =>
            new PageService($db)
        );

        $this->container->singleton(RedirectService::class, fn() =>
            new RedirectService($db)
        );

        $this->container->singleton(MenuService::class, fn() =>
            new MenuService($db)
        );
		
		// ─── НОВОЕ ───
		$this->container->singleton(SitemapService::class, fn() =>
			new SitemapService($db)
		);
    }

    public function boot(): void
    {
        // Ничего не требуется
    }
}