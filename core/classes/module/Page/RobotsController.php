<?php
declare(strict_types=1);

namespace Core\Module\Page;

use Core\System\Config\Config;
use Core\System\Http\Response;
use Core\System\Http\StatusCode;

/**
 * Контроллер для динамической генерации robots.txt.
 *
 * Единая точка входа домена: хост берётся из конфигурации (APP_BASE_URL),
 * поэтому при переносе сайта на другой домен файл не требует правки.
 */
class RobotsController
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function index(): Response
    {
        $baseUrl = rtrim((string)$this->config->get('APP_BASE_URL', ''), '/');
        if ($baseUrl === '') {
            // Fallback: если конфиг пустой — берём host из текущего запроса,
            // чтобы robots.txt никогда не ссылался на «битый» домен.
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }

        $sitemapUrl = $baseUrl . '/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /__dev/',
            'Disallow: /api/',
            'Disallow: /storage/',
            'Disallow: /logs/',
            'Disallow: /core/',
            'Disallow: /vendor/',
            'Disallow: /views/',
            '',
            '# Карта сайта',
            'Sitemap: ' . $sitemapUrl,
            '',
            '# Задержки для вежливых ботов',
            'Crawl-delay: 1',
            '',
        ];

        return Response::text(implode("\n", $lines), StatusCode::OK)
            ->setContentType('text/plain')
            ->setCacheControl('public', 3600);
    }
}
