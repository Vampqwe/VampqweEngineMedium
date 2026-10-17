<?php
declare(strict_types=1);

namespace Core\Module\Page;

use Core\System\Config\Config;
use Core\System\Http\Request;
use Core\System\Http\Response;

/**
 * Контроллер для генерации sitemap.xml
 */
class SitemapController
{
    private SitemapService $sitemapService;
    private Config $config;

    public function __construct(SitemapService $sitemapService, Config $config)
    {
        $this->sitemapService = $sitemapService;
        $this->config = $config;
    }

public function index(Request $request): Response
{
    // Очистка буфера вывода
    if (ob_get_level()) {
        ob_clean();
    }
    ob_start();
    ob_clean();

    $baseUrl = rtrim($this->config->get('APP_BASE_URL', 'https://localhost'), '/');
    $items = $this->sitemapService->getPagesForSitemap($baseUrl);
    $xml = $this->buildXml($items);

    $lastUpdate = $this->sitemapService->getLastUpdate();
    $etag = $lastUpdate ? md5($lastUpdate . count($items)) : md5($xml);

    $clientEtag = $request->getHeader('If-None-Match', '');
    if ($clientEtag === '"' . $etag . '"') {
        http_response_code(304);
        exit;
    }

    $response = Response::xml($xml, \Core\System\Http\StatusCode::OK);
    
    // ПРИНУДИТЕЛЬНАЯ УСТАНОВКА ЗАГОЛОВКА
    $response->setHeader('Content-Type', 'application/xml; charset=UTF-8');
    $response->setHeader('X-Robots-Tag', 'noindex, follow');
    $response->setCacheControl('public', 3600);
    $response->setEtag($etag);
    
    return $response;
}

    private function buildXml(array $items): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($items as $item) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$item['loc']}</loc>\n";
            $xml .= "    <lastmod>{$item['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$item['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$item['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}